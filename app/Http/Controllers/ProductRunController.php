<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRunRequest;
use App\Models\Product;
use App\Models\ProductRun;
use App\Services\Store\ProductVariantSync;
use Illuminate\Support\Facades\DB;

/**
 * Print runs of a product, and the variants on sale during each — see
 * docs/store-design.md.
 *
 * Runs live on their own pages rather than inside the product form: a run owns
 * its whole price list, and 23 variants across several runs on one screen would
 * be unreadable.
 */
class ProductRunController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:store.manage');
    }

    public function create(Product $product)
    {
        $run = new ProductRun([
            'name' => $this->suggestedName($product),
            'sort_order' => $product->runs()->count(),
        ]);

        return $this->form($product, $run, creating: true);
    }

    public function store(ProductRunRequest $request, Product $product)
    {
        $run = $product->runs()->create($this->attributes($request));

        $blocked = $this->syncVariants($request, $run);
        $copied = $this->copyFromPrevious($request, $product, $run);

        return redirect()->route('products.runs.edit', [$product, $run])
            ->with('success', $this->flash('Run created.', $blocked, $copied));
    }

    public function edit(Product $product, ProductRun $run)
    {
        abort_unless($run->product_id === $product->id, 404);

        return $this->form($product, $run, creating: false);
    }

    public function update(ProductRunRequest $request, Product $product, ProductRun $run)
    {
        abort_unless($run->product_id === $product->id, 404);

        $run->fill($this->attributes($request))->save();

        $blocked = $this->syncVariants($request, $run);
        $copied = $this->copyFromPrevious($request, $product, $run);

        return redirect()->route('products.runs.edit', [$product, $run])
            ->with('success', $this->flash('Run saved.', $blocked, $copied));
    }

    public function destroy(Product $product, ProductRun $run)
    {
        abort_unless($run->product_id === $product->id, 404);

        // A run that took money is a financial record; its variants are what the
        // pick list and the refund screen read. A checkout still pending at
        // Stripe could yet become one. Abandoned checkouts (failed/cancelled,
        // nothing captured) are neither — a run whose only orders are those
        // goes, and takes them with it.
        if ($run->hasTakenMoney()) {
            return back()->with('error', 'This run has taken money, so it can\'t be deleted.');
        }
        if ($run->hasPendingCheckout()) {
            return back()->with('error', 'A checkout on this run is still pending at Stripe. The reconcile sweep settles those within about an hour — try again after.');
        }

        $dead = $run->deadOrders()->with('items')->get();

        foreach ($dead as $order) {
            if ($order->items->contains(fn ($i) => $i->product_run_id !== $run->id)) {
                return back()->with('error', 'Abandoned order '.$order->shortReference().' also has lines on another run, so this run can\'t be deleted on its own.');
            }
        }

        DB::transaction(function () use ($run, $dead) {
            foreach ($dead as $order) {
                $order->items()->delete();
                $order->delete();
            }
            $run->variants()->delete();
            $run->delete();
        });

        $message = 'Run deleted.';
        if ($dead->isNotEmpty()) {
            $message .= ' Removed '.$dead->count().' abandoned '.str('checkout')->plural($dead->count()).' that never took money.';
        }

        return redirect()->route('products.edit', $product)->with('success', $message);
    }

    /* ------------------------------------------------------------------ *
     * Internals
     * ------------------------------------------------------------------ */

    private function form(Product $product, ProductRun $run, bool $creating)
    {
        return view('product.admin.run', [
            'product' => $product,
            'run' => $run,
            'creating' => $creating,
            'variantRows' => app(ProductVariantSync::class)->rowsFor($run),
            // Offered as "copy the price list from" when starting a new run.
            'previousRuns' => $product->runs()
                ->when($run->exists, fn ($q) => $q->whereKeyNot($run->id))
                ->withCount('variants')
                ->get()
                ->filter(fn (ProductRun $r) => $r->variants_count > 0),
        ]);
    }

    /** @return array<string, mixed> */
    private function attributes(ProductRunRequest $request): array
    {
        return [
            'name' => $request->input('name'),
            'opens_at' => $request->input('opens_at') ?: null,
            'closes_at' => $request->input('closes_at') ?: null,
            'expected_arrival_at' => $request->input('expected_arrival_at') ?: null,
            'pickup_note' => $request->input('pickup_note') ?: null,
            'sort_order' => (int) $request->input('sort_order', 0),
        ];
    }

    /**
     * "Copy prices from <run>" — the reason a new run isn't 23 rows of typing.
     *
     * Runs AFTER the variant sync, never before: the sync deletes every variant
     * the editor's rows don't mention, and the editor only ever saw the rows
     * that existed when the page was rendered. Copying first put 23 fresh rows
     * in place for the sync to delete in the same request — the flash said
     * "Copied 23 variants" and the run stayed empty.
     */
    private function copyFromPrevious(ProductRunRequest $request, Product $product, ProductRun $run): int
    {
        $sourceId = (int) $request->input('copy_from_run_id');

        if (! $sourceId) {
            return 0;
        }

        $source = $product->runs()->whereKey($sourceId)->first();

        if (! $source || $source->id === $run->id) {
            return 0;
        }

        return app(ProductVariantSync::class)->copy($source, $run);
    }

    /** @return string[] variants that couldn't be removed */
    private function syncVariants(ProductRunRequest $request, ProductRun $run): array
    {
        // Only touch variants when the editor was actually on the page, so a
        // form posted without it can never wipe them.
        if (! $request->has('variants_present')) {
            return [];
        }

        return app(ProductVariantSync::class)
            ->sync($run->fresh(), (array) $request->input('variants', []));
    }

    /** @param  string[]  $blocked */
    private function flash(string $message, array $blocked, int $copied): string
    {
        if ($copied) {
            $message .= ' Copied '.$copied.' '.($copied === 1 ? 'variant' : 'variants').'.';
        }

        if ($blocked) {
            $message .= ' Kept '.implode(', ', $blocked).
                ' — already ordered, so '.(count($blocked) === 1 ? 'it' : 'they').' can be disabled but not removed.';
        }

        return $message;
    }

    /** "Fall 2026" for the first run, then "Run 2", "Run 3" … */
    private function suggestedName(Product $product): string
    {
        $count = $product->runs()->count();

        return $count === 0 ? now()->format('Y').' Run' : 'Run '.($count + 1);
    }
}
