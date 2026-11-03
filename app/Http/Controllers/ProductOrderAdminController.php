<?php

namespace App\Http\Controllers;

use App\Exports\Store\FinancialsExport;
use App\Models\Product;
use App\Models\ProductOrder;
use App\Models\ProductRun;
use App\Services\Store\PickList;
use App\Services\Store\ProductOrderRefunder;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Store orders after the money has moved: the list, one order's detail, the
 * per-run pick list, hand-over, the financials export and refunds — steps 7
 * and 8 of docs/store-design.md.
 *
 * Everything is `store.manage` except refunding, which is `store.issueRefunds`
 * (held by no role) — the same split as events, where an admin can run the
 * event but money out needs a specific grant.
 */
class ProductOrderAdminController extends Controller
{
    public function __construct()
    {
        $this->middleware('can:store.manage')->except('refund');
        $this->middleware('can:store.issueRefunds')->only('refund');
    }

    public function index()
    {
        return view('product.admin.orders.index', [
            'products' => Product::withTrashed()->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }

    public function show(ProductOrder $order)
    {
        $order->load(['items.run', 'pickupSchool', 'user', 'payment', 'refunds.refundedBy']);

        return view('product.admin.orders.show', [
            'order' => $order,
            'canRefund' => auth()->user()->can('store.issueRefunds'),
        ]);
    }

    /** Fulfillment status and the admin note — the two things an admin edits on an order. */
    public function update(Request $request, ProductOrder $order)
    {
        $data = $request->validate([
            'fulfillment_status' => ['required', Rule::in(array_keys(ProductOrder::FULFILLMENT_STATUSES))],
            'admin_note' => 'nullable|string|max:2000',
        ]);

        $this->setFulfillment($order, $data['fulfillment_status']);
        $order->admin_note = ($data['admin_note'] ?? null) ?: null;
        $order->save();

        return redirect()->route('orders.show', $order)->with('success', 'Order updated.');
    }

    /**
     * The Collected checkbox on the pick list. A real POST per order rather
     * than Livewire, so it works from a printed-then-typed-back-in sheet and a
     * tablet at the table alike.
     */
    public function collected(Request $request, ProductOrder $order)
    {
        $data = $request->validate(['collected' => 'required|boolean']);

        $this->setFulfillment(
            $order,
            $data['collected'] ? ProductOrder::FULFILLMENT_COLLECTED : ProductOrder::FULFILLMENT_READY
        );
        $order->save();

        return back()->with('success', $order->name.($data['collected'] ? ' marked collected.' : ' marked not collected.'));
    }

    public function pickList(Product $product, ProductRun $run)
    {
        abort_unless($run->product_id === $product->id, 404);

        return view('product.admin.orders.pick-list', app(PickList::class)->build($run) + ['product' => $product]);
    }

    /**
     * Mark every order on a run "ready for pickup" — the shipment arrived. Only
     * touches orders still awaiting, so one already collected stays collected.
     */
    public function markReady(Product $product, ProductRun $run)
    {
        abort_unless($run->product_id === $product->id, 404);

        $count = ProductOrder::paid()
            ->where('fulfillment_status', ProductOrder::FULFILLMENT_AWAITING)
            ->whereHas('items', fn ($q) => $q->where('product_run_id', $run->id))
            ->update(['fulfillment_status' => ProductOrder::FULFILLMENT_READY, 'fulfilled_at' => now()]);

        return back()->with('success', $count.' '.str('order')->plural($count).' marked ready for pickup.');
    }

    public function financials(Request $request)
    {
        $run = $request->filled('run') ? ProductRun::findOrFail($request->integer('run')) : null;
        $product = $run?->product
            ?? ($request->filled('product') ? Product::withTrashed()->findOrFail($request->integer('product')) : null);

        $name = 'store-financials'
            .($product ? '-'.$product->slug : '')
            .($run ? '-'.str($run->name)->slug() : '')
            .'.xlsx';

        return Excel::download(new FinancialsExport($product, $run), $name);
    }

    public function refund(Request $request, ProductOrder $order)
    {
        $data = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'note' => 'nullable|string|max:500',
        ]);

        try {
            $refund = app(ProductOrderRefunder::class)
                ->refund($order, (float) $data['amount'], auth()->user(), $data['note'] ?? null);
        } catch (ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->implode(' '));
        } catch (\Throwable $e) {
            return back()->with('error', 'Refund failed, nothing was changed: '.$e->getMessage());
        }

        return redirect()->route('orders.show', $order)
            ->with('success', '$'.number_format((float) $refund->amount, 2).' refunded to '.$order->name.' on the '.$order->stripeAccountLabel().'.');
    }

    private function setFulfillment(ProductOrder $order, string $status): void
    {
        if ($order->fulfillment_status === $status) {
            return;
        }

        $order->fulfillment_status = $status;

        if ($status === ProductOrder::FULFILLMENT_COLLECTED) {
            $order->collected_at = now();
            $order->fulfilled_at ??= now();
        } elseif ($status === ProductOrder::FULFILLMENT_READY) {
            $order->collected_at = null;
            $order->fulfilled_at ??= now();
        } else {
            $order->collected_at = null;
            $order->fulfilled_at = null;
        }
    }
}
