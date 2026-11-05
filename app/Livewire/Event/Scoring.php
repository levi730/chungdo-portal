<?php

namespace App\Livewire\Event;

use App\Models\DivisionResult;
use App\Models\Event;
use App\Models\EventDivisionVersion;
use App\Services\DivisionResults;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The scorer's screen: pick a published division, tap competitors in finishing
 * order. A tap gives the next number; the numbers can then be edited, so
 * 1-2-3-4 can become 1-2-3-3. Actions arrive on Livewire's endpoint, not through
 * the route middleware, so each one repeats the permission check.
 */
class Scoring extends Component
{
    #[Locked]
    public int $eventId;

    #[Locked]
    public ?int $versionId = null;

    #[Locked]
    public ?int $position = null;

    /** @var array<int|string, int|string> registration id => place */
    public array $places = [];

    public ?string $error = null;

    public function mount(int $eventId): void
    {
        $this->authorizeScoring();
        $this->eventId = $eventId;
    }

    public function open(int $versionId, int $position, DivisionResults $results): void
    {
        $this->authorizeScoring();

        $event = Event::findOrFail($this->eventId);
        $version = EventDivisionVersion::where('event_id', $event->id)->findOrFail($versionId);

        // The list on screen can be older than the latest publish.
        abort_unless($event->publishedVersionIdFor($version->discipline) === $version->id, 404);

        $members = $results->members($version, $position);

        $this->versionId = $version->id;
        $this->position = $position;
        $this->error = null;
        $this->places = [];

        $regIds = $members->pluck('id', 'user_id');

        foreach ($this->result()?->placements ?? [] as $placement) {
            if ($regIds->has($placement->user_id)) {
                $this->places[$regIds[$placement->user_id]] = $placement->place;
            }
        }
    }

    public function close(): void
    {
        $this->versionId = null;
        $this->position = null;
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
            $results->record($this->version(), $this->position, $this->places, auth()->user());
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

    public function render()
    {
        $results = app(DivisionResults::class);
        $event = Event::findOrFail($this->eventId);
        $version = $this->version();

        return view('livewire.event.scoring', [
            'event' => $event,
            'versions' => $results->publishedVersions($event),
            'scored' => DivisionResult::where('event_id', $event->id)->get()
                ->keyBy(fn ($result) => $result->event_division_version_id.':'.$result->position),
            'label' => $version ? ($version->data[$this->position]['label'] ?? '') : null,
            'members' => $version
                ? $results->members($version, $this->position)->sortBy(fn ($reg) => $reg->user?->full_name)
                : collect(),
            'result' => $this->result(),
        ]);
    }

    private function version(): ?EventDivisionVersion
    {
        return $this->versionId
            ? EventDivisionVersion::where('event_id', $this->eventId)->find($this->versionId)
            : null;
    }

    private function result(): ?DivisionResult
    {
        return $this->versionId
            ? DivisionResult::with('placements')
                ->where('event_division_version_id', $this->versionId)
                ->where('position', $this->position)
                ->first()
            : null;
    }

    private function authorizeScoring(): void
    {
        abort_unless(auth()->user()?->can('event.recordResults'), 403);
    }
}
