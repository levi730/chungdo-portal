<?php

namespace App\Http\Controllers;

use App\Models\CashRemittance;
use App\Models\Event;
use App\Services\CashRegistration\RemittanceReceiver;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/**
 * Cash/check registrations: the instructor's entry page (a Livewire form), the
 * printable slip, and the list where remittances are received or voided.
 */
class CashRemittanceController extends Controller
{
    public function create(string $slug)
    {
        $event = Event::where('slug', $slug)->firstOrFail();
        Gate::authorize('create', [CashRemittance::class, $event]);

        return view('cash.create', compact('event'));
    }

    /**
     * Receivers see every remittance; anyone else sees the ones they are
     * responsible for or entered — an instructor's "what do I still owe".
     */
    public function index(Request $request)
    {
        $user = $request->user();
        $all = $user->can('viewAny', CashRemittance::class);
        $status = $request->query('status', CashRemittance::STATUS_OUTSTANDING);

        $remittances = CashRemittance::query()
            ->with(['event', 'responsible', 'createdBy', 'receivedBy'])
            ->when(! $all, fn ($q) => $q->where(fn ($q) => $q
                ->where('responsible_user_id', $user->id)
                ->orWhere('created_by_user_id', $user->id)))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($request->query('event'), fn ($q, $eventId) => $q->where('event_id', $eventId))
            ->orderByDesc('created_at')
            ->paginate(50)
            ->withQueryString();

        return view('cash.index', compact('remittances', 'all', 'status'));
    }

    public function show(CashRemittance $remittance)
    {
        Gate::authorize('view', $remittance);
        $remittance->load(['event', 'responsible', 'createdBy', 'receivedBy', 'voidedBy']);

        return view('cash.slip', compact('remittance'));
    }

    public function receive(Request $request, CashRemittance $remittance, RemittanceReceiver $receiver)
    {
        Gate::authorize('receive', $remittance);

        $data = $request->validate([
            'amount' => 'required|numeric|min:0|max:100000',
            'method' => 'required|in:'.implode(',', array_keys(CashRemittance::METHODS)),
            'check_numbers' => 'nullable|string|max:255',
            'deposit_note' => 'nullable|string|max:2000',
        ]);

        $r = $receiver->receive($remittance, $request->user(), (float) $data['amount'], $data['method'],
            $data['check_numbers'] ?? null, $data['deposit_note'] ?? null);

        $message = "{$r->reference} marked received.";
        if (abs($r->balance()) >= 0.005) {
            $message .= $r->balance() > 0
                ? ' $'.number_format($r->balance(), 2).' short of what was due.'
                : ' $'.number_format(-$r->balance(), 2).' more than was due.';
        }

        return back()->with('success', $message);
    }

    public function void(Request $request, CashRemittance $remittance, RemittanceReceiver $receiver)
    {
        Gate::authorize('void', $remittance);

        $data = $request->validate(['reason' => 'required|string|max:2000']);
        $r = $receiver->void($remittance, $request->user(), $data['reason']);

        return redirect()->route('cash.remittances')
            ->with('success', "{$r->reference} voided and its registrations removed.");
    }
}
