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
        $event = Event::findOrFail($this->eventId);

        // Polls arrive on Livewire's endpoint, so the route's check isn't enough:
        // switching public results off has to take effect on an open page too.
        abort_unless($event->resultsVisibleTo(auth()->user()), 403);

        return view('livewire.event.results', [
            'groups' => DivisionResult::completed()
                ->with('placements')
                ->where('event_id', $event->id)
                ->orderBy('sort_order')
                ->get()
                ->groupBy('discipline'),
            'combined' => $event->hasForms() && $event->hasSparring(),
        ]);
    }
}
