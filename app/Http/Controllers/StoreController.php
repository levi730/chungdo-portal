<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\Store\Cart;
use Illuminate\Http\Request;

/**
 * The public storefront — see docs/store-design.md.
 *
 * These are the portal's FIRST guest-accessible member-facing pages: every other
 * /event and /admin route sits behind auth+verified. Guests can browse and build
 * a cart; who may check out is decided at checkout, not here.
 *
 * Nothing here touches money. The order row and the charge happen at checkout,
 * per docs/payment-flow-pattern.md.
 */
class StoreController extends Controller
{
    public function __construct(private Cart $cart) {}

    /** Everything currently on sale. */
    public function index()
    {
        $products = Product::orderable()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        return view('store.index', [
            'products' => $products,
            'cart' => $this->cart,
        ]);
    }

    public function show(string $slug)
    {
        $product = Product::where('slug', $slug)->firstOrFail();

        // A design with no open run has no window and no price list. Show the
        // page (so a shared link isn't a 404) but with nothing to buy.
        $run = $product->openRun();

        return view('store.show', [
            'product' => $product,
            'run' => $run,
            'variants' => $run ? $run->variants()->enabled()->get() : collect(),
            'nextRun' => $run ? null : $product->nextRun(),
            'cart' => $this->cart,
        ]);
    }

    public function cart()
    {
        return view('store.cart', ['cart' => $this->cart]);
    }

    public function addToCart(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'quantity' => 'nullable|integer|min:1|max:100',
        ]);

        $variant = ProductVariant::with('run.product')->findOrFail($data['product_variant_id']);
        $error = $this->cart->add($variant, (int) ($data['quantity'] ?? 1));

        if ($error) {
            return back()->with('error', $this->message($error, $variant));
        }

        return redirect()->route('store.cart')
            ->with('success', $variant->displayName().' added to your cart.');
    }

    public function updateCart(Request $request)
    {
        $data = $request->validate([
            'product_variant_id' => 'required|integer|exists:product_variants,id',
            'quantity' => 'required|integer|min:0|max:100',
        ]);

        $variant = ProductVariant::with('run.product')->findOrFail($data['product_variant_id']);
        $error = $this->cart->update($variant, (int) $data['quantity']);

        if ($error) {
            return back()->with('error', $this->message($error, $variant));
        }

        return back()->with('success', 'Cart updated.');
    }

    public function removeFromCart(ProductVariant $variant)
    {
        $this->cart->remove($variant);

        return back()->with('success', $variant->displayName().' removed.');
    }

    /** Plain wording for each refusal — the buyer should know what to do next. */
    private function message(string $error, ProductVariant $variant): string
    {
        return match ($error) {
            Cart::ERROR_MIXED_ACCOUNT => 'This item is sold by a different account — please check out separately.',
            Cart::ERROR_MAX_PER_ORDER => 'That is more than the limit per order for '
                .$variant->run->product->name.'.',
            default => $variant->displayName().' is no longer on sale.',
        };
    }
}
