<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Product;
use Illuminate\Support\Collection;

/**
 * What the portal home page promotes, events and store items together.
 *
 * Both models already carry `highlighted` and `highlight_order`, so that number
 * is treated as ONE priority scale spanning both types rather than two separate
 * ones. That is what lets a shirt sit between two events; before this, the page
 * rendered every event and then every product, so a product could never outrank
 * an event however it was numbered.
 *
 * Order is: featured things by highlight_order (highest first), then whatever
 * upcoming events are left, soonest first. Nothing unfeatured is ever promoted
 * into the feature row — an empty row is correct when nothing is featured.
 */
class HomepageHighlights
{
    /**
     * How many featured things get the large treatment. One fills the width on
     * its own; two sit side by side; a third is still shown, just in the normal
     * grid, so the top of the page never becomes a wall of hero cards.
     */
    public const FEATURE_SLOTS = 2;

    private ?Collection $ordered = null;

    /** The large cards at the top. Empty when nothing is featured. */
    public function featured(): Collection
    {
        return $this->all()
            ->filter(fn ($item) => (bool) $item->highlighted)
            ->take(self::FEATURE_SLOTS)
            ->values();
    }

    /** Everything else, in the same priority order, for the grid below. */
    public function rest(): Collection
    {
        $promoted = $this->featured()->map(fn ($item) => $this->key($item))->all();

        return $this->all()
            ->reject(fn ($item) => in_array($this->key($item), $promoted, true))
            ->values();
    }

    /** Featured things first, then upcoming events filling in behind them. */
    public function all(): Collection
    {
        if ($this->ordered !== null) {
            return $this->ordered;
        }

        $featuredEvents = Event::upcoming()
            ->where('highlighted', true)
            ->get();

        // Already filtered to active products inside an open run.
        $featuredProducts = Product::forHomepage();

        // One scale across both types. Ties put the event first — it has a date
        // attached to it and so is the more perishable of the two.
        $featured = $featuredEvents->concat($featuredProducts)
            ->sortByDesc(fn ($item) => [
                (int) $item->highlight_order,
                $item instanceof Event ? 1 : 0,
            ])
            ->values();

        $fill = Event::upcoming()
            ->whereNotIn('id', $featuredEvents->pluck('id')->all())
            ->orderBy('startdatetime')
            ->take(Event::HOMEPAGE_LIMIT)
            ->get();

        return $this->ordered = $featured->concat($fill)->values();
    }

    /** Type-qualified, because an Event and a Product can share an id. */
    private function key(object $item): string
    {
        return class_basename($item).':'.$item->getKey();
    }
}
