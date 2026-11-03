<?php

namespace App\Services\Store;

use App\Models\ProductOrder;
use App\Models\ProductOrderItem;
use App\Models\ProductRun;
use App\Models\School;
use Illuminate\Support\Collection;

/**
 * The pick list for one print run: what to put in each school's box, and who
 * collects what — the operational deliverable of the store, per
 * docs/store-design.md. Printing it is how the shirts get handed out.
 *
 * Reads `product_order_items` rather than the order payload, and groups by
 * `product_variant_id` — which is why an ordered variant can be disabled but
 * never deleted (ProductVariantSync). Only PAID orders count; a pending or
 * failed order owes nobody anything, and a fully refunded one no longer does.
 */
class PickList
{
    /**
     * @return array{
     *   run: ProductRun,
     *   totals: array<int, array{variant: string, quantity: int}>,
     *   schools: array<int, array{
     *     school: ?School,
     *     totals: array<int, array{variant: string, quantity: int}>,
     *     orders: array<int, array{order: ProductOrder, lines: Collection<int, ProductOrderItem>, other_lines: int}>  # other_lines = item count on other runs
     *   }>,
     *   order_count: int,
     *   collected_count: int
     * }
     */
    public function build(ProductRun $run): array
    {
        $orders = ProductOrder::query()
            ->paid()
            ->whereHas('items', fn ($q) => $q->where('product_run_id', $run->id))
            ->with(['items' => fn ($q) => $q->orderBy('id'), 'pickupSchool'])
            ->orderBy('name')
            ->orderBy('id')
            ->get()
            ->reject(fn (ProductOrder $o) => $o->isFullyRefunded());

        $schools = [];
        $grandTotals = [];
        $collected = 0;

        foreach ($orders as $order) {
            $lines = $order->items->where('product_run_id', $run->id)->values();
            $schoolKey = $order->pickup_school_id ?? 0;

            if (! isset($schools[$schoolKey])) {
                $schools[$schoolKey] = [
                    'school' => $order->pickupSchool,
                    'totals' => [],
                    'orders' => [],
                ];
            }

            foreach ($lines as $line) {
                $this->tally($schools[$schoolKey]['totals'], $line);
                $this->tally($grandTotals, $line);
            }

            $schools[$schoolKey]['orders'][] = [
                'order' => $order,
                'lines' => $lines,
                // Items for OTHER runs on the same order — a bag arriving next
                // month — so the person at the table knows this is not the
                // whole order.
                'other_lines' => (int) $order->items->where('product_run_id', '!=', $run->id)->sum('quantity'),
            ];

            if ($order->isCollected()) {
                $collected++;
            }
        }

        // Named schools alphabetically; orders with no school chosen last.
        uasort($schools, function ($a, $b) {
            if ($a['school'] === null) {
                return 1;
            }
            if ($b['school'] === null) {
                return -1;
            }

            return strcasecmp($a['school']->name, $b['school']->name);
        });

        foreach ($schools as &$school) {
            $school['totals'] = $this->sorted($school['totals']);
        }
        unset($school);

        return [
            'run' => $run,
            'totals' => $this->sorted($grandTotals),
            'schools' => array_values($schools),
            'order_count' => $orders->count(),
            'collected_count' => $collected,
        ];
    }

    /** @param  array<int, array{variant: string, quantity: int, sort: int}>  $totals */
    private function tally(array &$totals, ProductOrderItem $line): void
    {
        // A deleted variant cannot happen (the sync refuses), but a null id
        // must not collapse every such line into one bucket if it ever does.
        $key = $line->product_variant_id ?? -$line->id;

        if (! isset($totals[$key])) {
            $totals[$key] = [
                'variant' => $line->variant_name,
                'quantity' => 0,
                'sort' => $line->variant?->sort_order ?? PHP_INT_MAX,
            ];
        }
        $totals[$key]['quantity'] += $line->quantity;
    }

    /**
     * In the run's own variant order (the price-list order), so the sheet reads
     * the same way the admin's list does.
     *
     * @return array<int, array{variant: string, quantity: int}>
     */
    private function sorted(array $totals): array
    {
        uasort($totals, fn ($a, $b) => [$a['sort'], $a['variant']] <=> [$b['sort'], $b['variant']]);

        return array_values(array_map(fn ($t) => ['variant' => $t['variant'], 'quantity' => $t['quantity']], $totals));
    }
}
