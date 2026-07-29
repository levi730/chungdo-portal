<?php

namespace App\Services;

use App\Models\EventRegistrationAddon;
use App\Models\PotluckOptions;

/**
 * Reads and syncs an event's potluck catalog (potluck_options) from the admin
 * "Manage Event" potluck editor. Items are Category + Item; an item that a
 * registrant has already chosen cannot be removed.
 */
class PotluckCatalog
{
    /**
     * How many registrations currently reference each catalog item.
     *
     * @return array<int, int> [potluck_option_id => count]
     */
    public function usageCounts(int $eventId): array
    {
        return EventRegistrationAddon::where('type', 'potluck')
            ->whereHas('registration', fn ($q) => $q->where('event_id', $eventId))
            ->get()
            ->groupBy(fn ($a) => $a->data['potluck_item_id'] ?? null)
            ->reject(fn ($group, $id) => ! $id)
            ->map->count()
            ->mapWithKeys(fn ($count, $id) => [(int) $id => $count])
            ->all();
    }

    /**
     * Catalog rows for the editor, each with its in-use count.
     *
     * @return array<int, array{id: int, category: string, item: string, count: int}>
     */
    public function rowsFor(int $eventId): array
    {
        $usage = $this->usageCounts($eventId);

        return PotluckOptions::where('event_id', $eventId)
            ->orderBy('category')->orderBy('item')
            ->get()
            ->map(fn ($o) => [
                'id' => (int) $o->id,
                'category' => (string) $o->category,
                'item' => (string) $o->item,
                'count' => $usage[$o->id] ?? 0,
            ])
            ->all();
    }

    /**
     * Sync submitted rows to potluck_options: update existing, create new, and
     * delete removed rows — except any still chosen by a registrant.
     *
     * @param  array<int, array{id?: mixed, category?: mixed, item?: mixed}>  $rows
     * @return string[] names of items that couldn't be removed (still in use)
     */
    public function sync(int $eventId, array $rows): array
    {
        $usage = $this->usageCounts($eventId);
        $existing = PotluckOptions::where('event_id', $eventId)->get()->keyBy('id');
        $keptIds = [];

        foreach ($rows as $row) {
            $item = trim((string) ($row['item'] ?? ''));
            if ($item === '') {
                continue; // ignore blank rows
            }
            $category = trim((string) ($row['category'] ?? ''));
            $id = (int) ($row['id'] ?? 0);

            if ($id && $existing->has($id)) {
                $opt = $existing[$id];
                $opt->category = $category;
                $opt->item = $item;
                $opt->save();
                $keptIds[] = $id;
            } else {
                $new = PotluckOptions::forceCreate([
                    'event_id' => $eventId,
                    'category' => $category,
                    'item' => $item,
                    'limit' => 0,
                    'current_count' => 0,
                ]);
                $keptIds[] = $new->id;
            }
        }

        $blocked = [];
        foreach ($existing as $id => $opt) {
            if (in_array((int) $id, $keptIds, true)) {
                continue;
            }
            if (($usage[$id] ?? 0) > 0) {
                $blocked[] = $opt->item; // still chosen — keep it
                continue;
            }
            $opt->delete();
        }

        return $blocked;
    }
}
