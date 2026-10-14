<?php

namespace App\Livewire\Event;

use App\EventAddons\EventParticipationAddon;
use App\Models\CashRemittance;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\EventRegistrationAddon;
use App\Models\SchoolInstructor;
use App\Models\User;
use App\Services\CashRegistration\CashRegistrar;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * An instructor registers a batch of students who paid them in cash or check.
 * The total is priced live by CashRegistrar::quote(), the same code submit()
 * uses, so the number on screen is the number on the slip.
 */
class CashRegistration extends Component
{
    #[Locked]
    public int $eventId;

    public string $search = '';

    /** [user_id => ['tshirt' => ?, 'participation' => ?, 'meal' => [...], 'guests' => int, 'volunteer' => []]] */
    public array $students = [];

    /** Who is on the hook for the money; only an event manager may change it. */
    public ?int $responsibleId = null;

    public string $note = '';

    public bool $confirmed = false;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
        $this->authorizeCreate();
        $this->responsibleId = auth()->id();
    }

    private function authorizeCreate(): void
    {
        abort_unless(Gate::allows('create', [CashRemittance::class, $this->event]), 403);
    }

    #[Computed]
    public function event(): Event
    {
        return Event::with(['addons', 'minimumRank'])->findOrFail($this->eventId);
    }

    /** The per-student add-ons this form asks about: enabled, open, and supported. */
    #[Computed]
    public function addons()
    {
        return $this->event->enabledAddons()
            ->filter(fn ($a) => $a->isOpen() && in_array($a->type, CashRegistrar::SUPPORTED_ADDONS, true))
            ->filter(fn ($a) => $a->type !== 'guests' || (int) $a->setting('max', 0) > 0);
    }

    #[Computed]
    public function canAssign(): bool
    {
        return Gate::allows('assignResponsible', CashRemittance::class);
    }

    /** Instructors an event manager can name responsible. */
    #[Computed]
    public function instructors()
    {
        if (! $this->canAssign) {
            return collect();
        }

        return User::whereIn('id', SchoolInstructor::pluck('user_id')->push(auth()->id())->unique())
            ->orderBy('lastname')->orderBy('firstname')
            ->get(['id', 'firstname', 'lastname']);
    }

    /**
     * Members matching the search. The instructor's own schools sort first, and
     * anyone who cannot be added is still shown, with the reason, so "why
     * isn't she in the list" answers itself.
     */
    #[Computed]
    public function searchResults()
    {
        $term = trim($this->search);
        if (strlen($term) < 2) {
            return collect();
        }

        $mySchools = SchoolInstructor::where('user_id', auth()->id())->pluck('school_id')->all() ?: [0];
        $registered = EventRegistration::where('event_id', $this->eventId)->pluck('user_id')->flip();

        return User::query()
            ->with(['school', 'rank'])
            ->whereNotIn('id', array_keys($this->students))
            ->where(function ($q) use ($term) {
                $q->where('firstname', 'like', "%{$term}%")
                    ->orWhere('lastname', 'like', "%{$term}%")
                    ->orWhereRaw("concat(firstname, ' ', lastname) like ?", ["%{$term}%"])
                    ->orWhere('email', 'like', "%{$term}%");
            })
            ->orderByRaw('school_id in ('.implode(',', array_map('intval', $mySchools)).') desc')
            ->orderBy('lastname')->orderBy('firstname')
            ->limit(10)
            ->get()
            ->each(function (User $u) use ($registered) {
                $u->blocked_reason = match (true) {
                    $registered->has($u->id) => 'Already registered',
                    ! $this->event->userMeetsMinimumRank($u) => 'Below minimum rank',
                    default => null,
                };
            });
    }

    #[Computed]
    public function selected()
    {
        return User::with(['school', 'rank'])->findMany(array_keys($this->students))->keyBy('id');
    }

    #[Computed]
    public function quote(): array
    {
        return empty($this->students)
            ? ['students' => [], 'total' => 0.0]
            : app(CashRegistrar::class)->quote($this->event, $this->students);
    }

    public function add(int $userId): void
    {
        $user = User::find($userId);
        if (! $user || isset($this->students[$userId])) {
            return;
        }

        // Start from their last t-shirt size, as the card form does.
        $lastTshirt = EventRegistrationAddon::where('type', 'tshirt')
            ->whereHas('registration', fn ($q) => $q->where('user_id', $userId))
            ->latest('id')->value('value');

        $this->students[$userId] = [
            'tshirt' => $lastTshirt,
            'participation' => EventParticipationAddon::BOTH,
            'meal' => ['attending' => false, 'additional' => 0],
            'guests' => 0,
            'volunteer' => [],
        ];
        $this->reset('search', 'confirmed');
    }

    public function remove(int $userId): void
    {
        unset($this->students[$userId]);
        $this->confirmed = false;
    }

    public function submit()
    {
        $this->authorizeCreate();

        $responsible = $this->canAssign && $this->responsibleId
            ? User::findOrFail($this->responsibleId)
            : auth()->user();

        if (! $this->confirmed) {
            throw ValidationException::withMessages([
                'confirmed' => 'Confirm that '.($responsible->is(auth()->user()) ? 'you have' : $responsible->full_name.' has').' collected this money.',
            ]);
        }

        // Prices are not taken from the client: the handlers re-derive every
        // amount, skip closed add-ons and clamp quantities, as on the card form.
        $answers = [];
        foreach ($this->students as $id => $a) {
            $answers[(int) $id] = $a;
        }

        $remittance = app(CashRegistrar::class)->submit(
            $this->event, $responsible, auth()->user(), $answers, trim($this->note) ?: null
        );

        session()->flash('success', 'Registered '.count($answers).' student(s). Print this slip and put it in the envelope with the money.');

        return redirect()->route('cash.slip', $remittance);
    }

    public function render()
    {
        return view('livewire.event.cash-registration');
    }
}
