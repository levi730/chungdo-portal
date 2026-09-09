<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\User;
use App\Models\UserNote;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Notes about a member.
 *
 * Writing one needs the same right as the registrants list it is written from —
 * these carry medical and behavioural detail about children, and the routes are
 * not otherwise limited to the people who run events. Changing one afterwards is
 * narrower still; see UserNotePolicy.
 */
class UserNoteController extends Controller
{
    public function store(Request $request, User $user)
    {
        if (! auth()->user()->can('event.viewAllSchoolRegistrants')) {
            abort(403);
        }

        $validated = $this->validated($request);

        $note = $user->notes()->create([
            'note' => $validated['note'],
            'scope' => $validated['scope'],
            'event_id' => $validated['event_id'] ?? null,
            'added_by' => auth()->id(),
        ]);

        return response()->json($this->payload($note));
    }

    /**
     * Edit a note's text, or move it between permanent and temporary.
     *
     * A note only ever becomes temporary from inside one event's modal, and that
     * modal shows no other event's temporary notes, so the event it is being
     * edited from is the event it belongs to. Going the other way keeps the
     * event as provenance: it still records where the observation was made.
     */
    public function update(Request $request, UserNote $note)
    {
        $this->authorize('update', $note);

        $validated = $this->validated($request);

        $note->update([
            'note' => $validated['note'],
            'scope' => $validated['scope'],
            'event_id' => $validated['scope'] === UserNote::SCOPE_TEMPORARY
                ? $validated['event_id']
                : $note->event_id,
        ]);

        return response()->json($this->payload($note->fresh()));
    }

    public function destroy(UserNote $note)
    {
        $this->authorize('delete', $note);

        $note->delete();

        return response()->json(['deleted' => true, 'id' => $note->id]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'note' => ['required', 'string'],
            'scope' => ['required', Rule::in(UserNote::SCOPES)],
            // A temporary note with no event could never be shown again — see
            // UserNote::visibleForEvent — so it must name one.
            'event_id' => [
                Rule::requiredIf(fn () => $request->input('scope') === UserNote::SCOPE_TEMPORARY),
                'nullable',
                'integer',
                Rule::exists(Event::class, 'id'),
            ],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(UserNote $note): array
    {
        return [
            'id' => $note->id,
            'user_id' => $note->user_id,
            'note' => $note->note,
            'scope' => $note->scope,
            'by' => $note->added_by_user->fullname,
            'formatted_date' => $note->created_at->format('m/d/Y g:i a'),
            'edited' => $note->updated_at->gt($note->created_at),
        ];
    }
}
