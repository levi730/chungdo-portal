<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\UserNote;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function impersonate(User $user)
    {
        auth()->user()->impersonate($user);

        return redirect()->route('dashboard');
    }

    public function leaveImpersonate()
    {
        auth()->user()->leaveImpersonation();

        return redirect()->route('dashboard');
    }

    /**
     * Record a note about a member.
     *
     * Gated on the same permission as the registrants list it is written from —
     * these notes carry medical and behavioural detail about children, and the
     * route is not otherwise limited to the people who run events.
     *
     * A temporary note is only meaningful alongside the event it was written
     * for, so it must name one; a permanent note may, as provenance.
     */
    public function addUserNote(Request $request, User $user)
    {
        if (! auth()->user()->can('event.viewAllSchoolRegistrants')) {
            abort(403);
        }

        $validated = $request->validate([
            'note' => ['required', 'string'],
            'scope' => ['required', Rule::in(UserNote::SCOPES)],
            'event_id' => [
                Rule::requiredIf(fn () => $request->input('scope') === UserNote::SCOPE_TEMPORARY),
                'nullable',
                'integer',
                'exists:events,id',
            ],
        ]);

        $newnote = $user->notes()->create([
            'note' => $validated['note'],
            'scope' => $validated['scope'],
            'event_id' => $validated['event_id'] ?? null,
            'added_by' => auth()->user()->id,
        ]);

        $data = [
            'user_id' => $user->id,
            'note' => $newnote->note,
            'scope' => $newnote->scope,
            'by' => $newnote->added_by_user->fullname,
            'formatted_date' => $newnote->created_at->format('m/d/Y g:i a'),
        ];

        return response()->json($data);

    }
}
