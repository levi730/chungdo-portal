<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProductRequest;
use App\Models\Product;
use App\Services\Stripe\StripeAccounts;
use Illuminate\Http\Request;

/**
 * Admin CRUD for the merchandise store — step 2 of docs/store-design.md.
 *
 * Mirrors EventAdminController deliberately, including the three-layer lock on
 * the Stripe account (disabled select, rejected in ProductRequest, refused in
 * fill() below).
 */
class ProductAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:store.manage');
    }

    public function index()
    {
        return view('product.admin.index');
    }

    public function create()
    {
        return $this->form(new Product(), creating: true);
    }

    public function store(ProductRequest $request)
    {
        $product = new Product();
        $this->fill($product, $request);
        $product->save();

        $this->syncMedia($product, $request);

        return redirect()->route('products.edit', $product)
            ->with('success', 'Product created. Add a print run to start taking orders.');
    }

    public function edit(Product $product)
    {
        return $this->form($product, creating: false);
    }

    public function update(ProductRequest $request, Product $product)
    {
        $this->fill($product, $request);
        $product->save();

        $this->syncMedia($product, $request);

        return redirect()->route('products.edit', $product)->with('success', 'Product saved.');
    }

    public function destroy(Product $product)
    {
        $product->delete();

        return redirect()->route('products.index')->with('success', 'Product archived.');
    }

    public function restore($id)
    {
        abort_unless(auth()->user()->can('store.manage'), 403);

        Product::withTrashed()->findOrFail($id)->restore();

        return redirect()->route('products.index')->with('success', 'Product restored.');
    }

    public function deleteMedia(Product $product, $mediaId)
    {
        // Scoped to the product, so this can't reach another model's media.
        $product->media()->findOrFail($mediaId)->delete();

        return back()->with('success', 'Image removed.');
    }

    /** Store the focal point (0-100%) and zoom for a product image. */
    public function setMediaFocus(Request $request, Product $product, $mediaId)
    {
        $media = $product->media()->findOrFail($mediaId);

        $data = $request->validate([
            'focusX' => 'required|numeric|between:0,100',
            'focusY' => 'required|numeric|between:0,100',
            'zoom' => 'nullable|numeric|between:1,3',
        ]);

        $media->setCustomProperty('focusX', round($data['focusX'], 2));
        $media->setCustomProperty('focusY', round($data['focusY'], 2));
        $media->setCustomProperty('focusZoom', round($data['zoom'] ?? 1, 2));
        $media->save();

        return response()->json(['ok' => true]);
    }

    /* ------------------------------------------------------------------ *
     * Internals
     * ------------------------------------------------------------------ */

    private function form(Product $product, bool $creating)
    {
        return view('product.admin.form', [
            'product' => $product,
            'creating' => $creating,
            'stripeAccounts' => app(StripeAccounts::class),
            'runs' => $creating ? collect() : $product->runs()->withCount('variants')->get(),
        ]);
    }

    private function fill(Product $product, ProductRequest $request): void
    {
        $product->fill($request->only([
            'name', 'slug', 'status', 'description', 'max_per_order',
            'highlighted', 'highlight_order', 'sort_order',
        ]));

        $product->option_names = $request->optionNames();

        // The Stripe account is locked once money has moved (see ProductRequest),
        // and the select is disabled in that case so nothing is posted.
        if (! $product->exists || ! $product->hasPayments()) {
            $product->stripe_account = app(StripeAccounts::class)
                ->resolve($request->input('stripe_account'));
        }
    }

    private function syncMedia(Product $product, Request $request): void
    {
        foreach ((array) $request->file('images', []) as $file) {
            $product->addMedia($file)->toMediaCollection('product-images');
        }
    }

}
