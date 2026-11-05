<?php

namespace App\Livewire\Event;

use App\Models\DivisionResult;
use App\Models\Event;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * The live results page. It polls, so nothing here should be a public property
 * except the id: Livewire writes public properties into the page.
 */
class Results extends Component
{
    #[Locked]
    public int $eventId;

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function render()
    {
        // Polls arrive on Livewire's endpoint, not through the route's
        // auth + verified middleware.
        abort_unless(auth()->user()?->hasVerifiedEmail(), 403);

        $event = Event::findOrFail($this->eventId);

        return view('livewire.event.results', [
            'groups' => DivisionResult::completed()
                ->with('placements')
                ->where('event_id', $event->id)
                ->orderBy('position')
                ->get()
                ->groupBy('discipline'),
            'combined' => $event->hasForms() && $event->hasSparring(),
        ]);
    }
}
