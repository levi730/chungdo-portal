<?php

namespace App\Livewire\Event;

use App\Models\Event;
use App\Models\ParticipationTrophy;
use App\Services\ParticipationTrophies as TrophyList;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The trophy table's list. Actions arrive on Livewire's endpoint, not through
 * the route middleware, so each one repeats the permission check.
 */
class ParticipationTrophies extends Component
{
    #[Locked]
    public int $eventId;

    public function mount(int $eventId): void
    {
        $this->authorizeList();
        $this->eventId = $eventId;
    }

    /** Set, not toggled: two people ticking the same child must agree. */
    public function setGiven(int $registrationId, bool $given, TrophyList $trophies): void
    {
        $this->authorizeList();

        $event = Event::findOrFail($this->eventId);

        if (! $trophies->build($event)['ready']->contains('registration_id', $registrationId)) {
            return;
        }

        if ($given) {
            ParticipationTrophy::firstOrCreate(
                ['event_registration_id' => $registrationId],
                ['event_id' => $event->id, 'given_by_user_id' => auth()->id()],
            );
        } else {
            ParticipationTrophy::where('event_registration_id', $registrationId)->delete();
        }
    }

    public function render(TrophyList $trophies)
    {
        $event = Event::findOrFail($this->eventId);

        return view('livewire.event.participation-trophies', [
            ...$trophies->build($event),
            'winningPlaces' => $event->winning_places,
        ]);
    }

    private function authorizeList(): void
    {
        abort_unless(auth()->user()?->can('event.recordResults'), 403);
    }
}
