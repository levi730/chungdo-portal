<?php

namespace App\Livewire\Event;

use App\Models\DivisionResult;
use App\Models\Event;
use App\Models\EventDivision;
use App\Models\EventDivisionVersion;
use App\Services\DivisionResults;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The scorer's screen: pick a division, tap competitors in finishing order.
 * A tap gives the next number; the numbers can then be edited, so 1-2-3-4 can
 * become 1-2-3-3. Actions arrive on Livewire's endpoint, not through the route
 * middleware, so each one repeats the permission check.
 */
class Scoring extends Component
{
    #[Locked]
    public int $eventId;

    #[Locked]
    public ?int $divisionId = null;

    /** @var array<int|string, int|string> registration id => place */
    public array $places = [];

    public ?string $error = null;

    public function mount(int $eventId): void
    {
        $this->authorizeScoring();
        $this->eventId = $eventId;
    }

    public function open(int $divisionId): void
    {
        $this->authorizeScoring();

        $division = $this->division($divisionId);
        $this->divisionId = $division->id;
        $this->error = null;
        $this->places = [];

        $result = DivisionResult::with('placements')->where('event_division_id', $division->id)->first();

        if ($result) {
            $regIds = $division->members()->pluck('id', 'user_id');

            foreach ($result->placements as $placement) {
                if ($regIds->has($placement->user_id)) {
                    $this->places[$regIds[$placement->user_id]] = $placement->place;
                }
            }
        }
    }

    public function close(): void
    {
        $this->divisionId = null;
        $this->places = [];
        $this->error = null;
    }

    /** Number the next competitor, or take the number back off them. */
    public function mark(int $regId): void
    {
        $this->authorizeScoring();

        if ($this->result()?->isCompleted()) {
            return;
        }

        $this->places = array_filter($this->places, fn ($place) => $place !== '' && $place !== null);

        if (! isset($this->places[$regId])) {
            $this->places[$regId] = $this->places === [] ? 1 : max(array_map('intval', $this->places)) + 1;

            return;
        }

        $removed = (int) $this->places[$regId];
        unset($this->places[$regId]);

        // Close the gap, unless someone else is tied on that place.
        if (! in_array($removed, array_map('intval', $this->places), true)) {
            foreach ($this->places as $id => $place) {
                if ((int) $place > $removed) {
                    $this->places[$id] = (int) $place - 1;
                }
            }
        }
    }

    public function save(DivisionResults $results): void
    {
        $this->authorizeScoring();

        try {
            $results->record($this->division($this->divisionId), $this->places, auth()->user());
            $this->close();
        } catch (InvalidArgumentException $e) {
            $this->error = $e->getMessage();
        }
    }

    public function reopen(DivisionResults $results): void
    {
        abort_unless(auth()->user()?->can('event.manage'), 403);

        $result = $this->result();

        if ($result) {
            $results->reopen($result);
        }
    }

    public function togglePublic(): void
    {
        abort_unless(auth()->user()?->can('event.manage'), 403);

        $event = Event::findOrFail($this->eventId);
        $event->update(['results_public' => ! $event->results_public]);
    }

    public function render()
    {
        $event = Event::findOrFail($this->eventId);

        $divisions = EventDivision::where('event_id', $event->id)->orderBy('sort_order')->get();
        $results = DivisionResult::where('event_id', $event->id)->get()->keyBy('event_division_id');

        return view('livewire.event.scoring', [
            'event' => $event,
            'groups' => $divisions->groupBy('discipline'),
            'results' => $results,
            'drifted' => $divisions->pluck('discipline')->unique()
                ->filter(fn ($discipline) => $this->boardDrifted($event, $discipline))
                ->values(),
            'division' => $this->divisionId ? $this->division($this->divisionId) : null,
            'members' => $this->divisionId
                ? $this->division($this->divisionId)->members()->with(['user.school', 'user.rank'])->get()
                    ->sortBy(fn ($reg) => $reg->user?->full_name)
                : collect(),
            'result' => $this->result(),
        ]);
    }

    /**
     * Scoring reads the saved board, but the cards were printed from the
     * published version. A later save means the two may disagree.
     */
    private function boardDrifted(Event $event, string $discipline): bool
    {
        $published = $event->publishedVersionIdFor($discipline);

        if (! $published) {
            return false;
        }

        $latest = (int) EventDivisionVersion::where('event_id', $event->id)
            ->where('discipline', $discipline)
            ->max('id');

        return $latest !== $published;
    }

    private function division(int $id): EventDivision
    {
        return EventDivision::where('event_id', $this->eventId)->findOrFail($id);
    }

    private function result(): ?DivisionResult
    {
        return $this->divisionId
            ? DivisionResult::where('event_division_id', $this->divisionId)->first()
            : null;
    }

    private function authorizeScoring(): void
    {
        abort_unless(auth()->user()?->can('event.recordResults'), 403);
    }
}
