<?php

namespace App\Livewire\Event;

use App\Models\DivisionResult;
use App\Models\Event;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * The live results page. It polls, so nothing here should be a public property
 * except the id and the tab: Livewire writes public properties into the page.
 *
 * Latest is the default so that a page left open puts each new result at the
 * top, where someone watching it will see it arrive.
 */
class Results extends Component
{
    #[Locked]
    public int $eventId;

    #[Url(except: 'latest')]
    public string $tab = 'latest';

    public function mount(int $eventId): void
    {
        $this->eventId = $eventId;
    }

    public function render()
    {
        $event = Event::findOrFail($this->eventId);
        $byDivision = $this->tab === 'divisions';

        $results = DivisionResult::published()
            ->with('placements')
            ->where('event_id', $event->id);

        return view('livewire.event.results', [
            'results' => $byDivision
                ? $results->orderBy('position')->get()
                : $results->newestFirst()->get(),
            'byDivision' => $byDivision,
            'combined' => $event->hasForms() && $event->hasSparring(),
        ]);
    }
}
