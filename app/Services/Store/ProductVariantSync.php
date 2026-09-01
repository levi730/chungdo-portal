<?php

namespace App\Services\Store;

use App\Models\ProductOrderItem;
use App\Models\ProductRun;
use App\Models\ProductVariant;

/**
 * Reads and syncs one print run's variants from the run admin form.
 *
 * A variant an order already references is never deleted. Order items do
 * snapshot the name and price, so history would survive the deletion — but the
 * variant is still what the pick list groups by, and removing one out from under
 * a live order would leave the people packing shirts with nothing to sort by.
 */
class ProductVariantSync
{
    /**
     * How many ordered line items reference each variant of this run.
     *
     * @return array<int, int> [product_variant_id => count]
     */
    public function usageCounts(ProductRun $run): array
    {
        return ProductOrderItem::where('product_run_id', $run->id)
            ->whereNotNull('product_variant_id')
            ->selectRaw('product_variant_id, count(*) as aggregate')
            ->groupBy('product_variant_id')
            ->pluck('aggregate', 'product_variant_id')
            ->mapWithKeys(fn ($count, $id) => [(int) $id => (int) $count])
            ->all();
    }

    /**
     * Variant rows for the editor, each with its in-use count so the form can
     * show a count instead of a delete button.
     *
     * @return array<int, array<string, mixed>>
     */
    public function rowsFor(ProductRun $run): array
    {
        if (! $run->exists) {
            return [];
        }

        $usage = $this->usageCounts($run);

        return $run->variants()->get()->map(fn (ProductVariant $v) => [
            'id' => (int) $v->id,
            'name' => (string) $v->name,
            'sku' => (string) ($v->sku ?? ''),
            'price' => (string) $v->price,
            'enabled' => (bool) $v->enabled,
            'sort_order' => (int) $v->sort_order,
            'options' => (object) ($v->options ?? []),
            'count' => $usage[$v->id] ?? 0,
        ])->all();
    }

    /**
     * Sync submitted rows to this run's variants: update existing, create new,
     * and delete removed rows — except any an order already references.
     *
     * @param  array<int, array<string, mixed>>  $rows
     * @return string[] names of variants that couldn't be removed (in use)
     */
    public function sync(ProductRun $run, array $rows): array
    {
        $usage = $this->usageCounts($run);
        $existing = $run->variants()->get()->keyBy('id');
        $optionNames = $run->product->option_names ?? [];
        $keptIds = [];

        foreach (array_values($rows) as $position => $row) {
            $options = $this->options($row, $optionNames);
            $name = trim((string) ($row['name'] ?? '')) ?: $this->nameFrom($options);

            if ($name === '') {
                continue; // a wholly blank row — the editor's empty last line
            }

            $attributes = [
                'name' => $name,
                'options' => $options ?: null,
                'sku' => trim((string) ($row['sku'] ?? '')) ?: null,
                'price' => round((float) ($row['price'] ?? 0), 2),
                'enabled' => (bool) ($row['enabled'] ?? false),
                // Fall back to the row's position so the admin can reorder rows
                // without filling in every number.
                'sort_order' => (int) ($row['sort_order'] ?? $position),
            ];

            $id = (int) ($row['id'] ?? 0);

            if ($id && $existing->has($id)) {
                $existing[$id]->fill($attributes)->save();
                $keptIds[] = $id;
            } else {
                $keptIds[] = $run->variants()->create($attributes)->id;
            }
        }

        $blocked = [];

        foreach ($existing as $id => $variant) {
            if (in_array((int) $id, $keptIds, true)) {
                continue;
            }
            if (($usage[$id] ?? 0) > 0) {
                $blocked[] = $variant->name; // ordered — keep it
                continue;
            }
            $variant->delete();
        }

        return $blocked;
    }

    /**
     * Copy one run's variants onto another, so opening next year's run doesn't
     * mean retyping the whole size list. Prices come across as they were and are
     * then edited for the new run — which is the whole reason variants belong to
     * a run rather than the product.
     *
     * Skips anything the target already has, so it is safe to run twice.
     *
     * @return int how many were copied
     */
    public function copy(ProductRun $from, ProductRun $to): int
    {
        $existing = $to->variants()->get()
            ->map(fn (ProductVariant $v) => $this->signature($v))
            ->all();

        $copied = 0;

        foreach ($from->variants()->get() as $variant) {
            if (in_array($this->signature($variant), $existing, true)) {
                continue;
            }

            $to->variants()->create([
                'name' => $variant->name,
                'options' => $variant->options,
                'sku' => $variant->sku,
                'price' => $variant->price,
                'enabled' => $variant->enabled,
                'sort_order' => $variant->sort_order,
            ]);

            $copied++;
        }

        return $copied;
    }

    /** Identity of a variant within its run: its option values, else its name. */
    private function signature(ProductVariant $variant): string
    {
        $options = collect($variant->options ?? [])->filter();

        return $options->isNotEmpty()
            ? $options->implode("\u{001f}")
            : (string) $variant->name;
    }

    /**
     * The submitted option map, restricted to the product's declared axes so a
     * renamed axis doesn't leave an orphan key behind on every variant.
     *
     * @param  array<string, mixed>  $row
     * @param  string[]  $optionNames
     * @return array<string, string>
     */
    private function options(array $row, array $optionNames): array
    {
        $submitted = (array) ($row['options'] ?? []);
        $options = [];

        foreach ($optionNames as $axis) {
            $value = trim((string) ($submitted[$axis] ?? ''));
            if ($value !== '') {
                $options[$axis] = $value;
            }
        }

        return $options;
    }

    /** "Adult Hoodie / L" — used when the admin leaves the name blank. */
    private function nameFrom(array $options): string
    {
        return collect($options)->filter()->implode(' / ');
    }
}
