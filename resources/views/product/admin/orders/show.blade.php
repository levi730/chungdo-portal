@extends('layouts.dashboard')

@section('page-title')
    Order {{ $order->shortReference() }} — {{ $order->name }}
@endsection

@section('content')
<div class="container-xl">
    <div class="content">
        <div class="mb-3">
            <a href="{{ route('orders.index') }}" class="btn btn-link px-0">&larr; All orders</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="row">
            <div class="col-lg-7">
                <div class="card mb-3">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h3 class="card-title mb-0">Order {{ $order->shortReference() }}</h3>
                        <div>
                            @include('product.admin.orders.cells.status', ['order' => $order])
                            @include('product.admin.orders.cells.fulfillment', ['order' => $order])
                        </div>
                    </div>
                    <div class="card-body">
                        <dl class="row mb-0">
                            <dt class="col-sm-4">Buyer</dt>
                            <dd class="col-sm-8">
                                {{ $order->name }}
                                @if($order->user)
                                    @can('manage-users')
                                        <a href="{{ route('users.edit', $order->user) }}" class="ms-1 small">member</a>
                                    @else
                                        <span class="badge bg-azure-lt ms-1">member</span>
                                    @endcan
                                @else
                                    <span class="badge bg-secondary-lt ms-1">guest</span>
                                @endif
                                <div class="text-muted">{{ $order->email }}@if($order->phone) &middot; {{ $order->phone }}@endif</div>
                            </dd>
                            <dt class="col-sm-4">Pickup at</dt>
                            <dd class="col-sm-8">{{ $order->pickupSchool?->name ?? '—' }}</dd>
                            <dt class="col-sm-4">Placed</dt>
                            <dd class="col-sm-8">{{ $order->created_at->format('M j, Y g:ia') }}</dd>
                            @if($order->paid_at)
                                <dt class="col-sm-4">Paid</dt>
                                <dd class="col-sm-8">{{ $order->paid_at->format('M j, Y g:ia') }}</dd>
                            @endif
                            @if($order->collected_at)
                                <dt class="col-sm-4">Collected</dt>
                                <dd class="col-sm-8">{{ $order->collected_at->format('M j, Y g:ia') }}</dd>
                            @endif
                            <dt class="col-sm-4">Stripe account</dt>
                            <dd class="col-sm-8">{{ $order->stripeAccountLabel() }}</dd>
                            @if($order->stripe_payment_intent_id)
                                <dt class="col-sm-4">Payment intent</dt>
                                <dd class="col-sm-8"><code>{{ $order->stripe_payment_intent_id }}</code></dd>
                            @endif
                            @if($order->stripe_checkout_session_id)
                                <dt class="col-sm-4">Checkout session</dt>
                                <dd class="col-sm-8"><code>{{ $order->stripe_checkout_session_id }}</code></dd>
                            @endif
                            <dt class="col-sm-4">Order reference</dt>
                            <dd class="col-sm-8"><code>{{ $order->reference }}</code></dd>
                        </dl>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr>
                                    <th>Item</th>
                                    <th>Run</th>
                                    <th class="text-end">Qty</th>
                                    <th class="text-end">Each</th>
                                    <th class="text-end">Amount</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($order->items as $line)
                                    <tr>
                                        <td>{{ $line->product_name }}@if($line->variant_name) <span class="text-muted">— {{ $line->variant_name }}</span>@endif</td>
                                        <td>
                                            @if($line->run)
                                                <a href="{{ route('products.runs.pick-list', [$line->product_id, $line->run]) }}">{{ $line->run->name }}</a>
                                            @else
                                                —
                                            @endif
                                        </td>
                                        <td class="text-end">{{ $line->quantity }}</td>
                                        <td class="text-end">${{ number_format((float) $line->unit_price, 2) }}</td>
                                        <td class="text-end">${{ number_format((float) $line->amount, 2) }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                            <tfoot>
                                @if((float) $order->tax > 0)
                                    <tr><td colspan="4" class="text-end">Subtotal</td><td class="text-end">${{ number_format((float) $order->subtotal, 2) }}</td></tr>
                                    <tr><td colspan="4" class="text-end">Tax</td><td class="text-end">${{ number_format((float) $order->tax, 2) }}</td></tr>
                                @endif
                                <tr><td colspan="4" class="text-end"><strong>Total</strong></td><td class="text-end"><strong>${{ number_format((float) $order->total, 2) }}</strong></td></tr>
                                @if($order->amount_paid !== null && (float) $order->amount_paid !== (float) $order->total)
                                    <tr><td colspan="4" class="text-end">Captured</td><td class="text-end">${{ number_format((float) $order->amount_paid, 2) }}</td></tr>
                                @endif
                                @if((float) $order->refunded_amount > 0)
                                    <tr><td colspan="4" class="text-end text-danger">Refunded</td><td class="text-end text-danger">&minus;${{ number_format((float) $order->refunded_amount, 2) }}</td></tr>
                                    <tr><td colspan="4" class="text-end"><strong>Net</strong></td><td class="text-end"><strong>${{ number_format($order->netPaid(), 2) }}</strong></td></tr>
                                @endif
                            </tfoot>
                        </table>
                    </div>
                </div>

                @if($order->refunds->isNotEmpty())
                    <div class="card mb-3">
                        <div class="card-header"><h3 class="card-title mb-0">Refunds</h3></div>
                        <div class="table-responsive">
                            <table class="table table-vcenter card-table">
                                <thead><tr><th>When</th><th>By</th><th>Note</th><th>Stripe refund</th><th class="text-end">Amount</th></tr></thead>
                                <tbody>
                                    @foreach($order->refunds as $refund)
                                        <tr>
                                            <td>{{ $refund->created_at->format('M j, Y g:ia') }}</td>
                                            <td>{{ $refund->refundedBy ? trim($refund->refundedBy->firstname.' '.$refund->refundedBy->lastname) : '—' }}</td>
                                            <td>{{ $refund->admin_note ?? '—' }}</td>
                                            <td><code>{{ $refund->stripe_refund_id ?? '—' }}</code></td>
                                            <td class="text-end">${{ number_format((float) $refund->amount, 2) }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif
            </div>

            <div class="col-lg-5">
                @if($order->isPaid() && ! $order->isFullyRefunded())
                    <div class="card mb-3">
                        <div class="card-header"><h3 class="card-title mb-0">Hand-over</h3></div>
                        <form method="POST" action="{{ route('orders.update', $order) }}" class="card-body">
                            @csrf @method('PUT')
                            <div class="mb-3">
                                <label class="form-label">Status</label>
                                <select name="fulfillment_status" class="form-select">
                                    @foreach(\App\Models\ProductOrder::FULFILLMENT_STATUSES as $value => $label)
                                        <option value="{{ $value }}" @selected(old('fulfillment_status', $order->fulfillment_status) === $value)>{{ $label }}</option>
                                    @endforeach
                                </select>
                                <small class="form-hint">Marking a whole run ready at once is on its pick list.</small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Admin note <span class="text-muted">(not shown to the buyer)</span></label>
                                <textarea name="admin_note" class="form-control" rows="3" maxlength="2000">{{ old('admin_note', $order->admin_note) }}</textarea>
                            </div>
                            <button class="btn btn-primary">Save</button>
                        </form>
                    </div>
                @elseif($order->admin_note)
                    <div class="card mb-3">
                        <div class="card-header"><h3 class="card-title mb-0">Admin note</h3></div>
                        <div class="card-body">{{ $order->admin_note }}</div>
                    </div>
                @endif

                @if($canRefund && $order->refundable() > 0)
                    <div class="card mb-3">
                        <div class="card-header"><h3 class="card-title mb-0">Refund</h3></div>
                        <form method="POST" action="{{ route('orders.refund', $order) }}" class="card-body"
                              onsubmit="return confirm('Refund $' + this.amount.value + ' to {{ addslashes($order->name) }}? This cannot be undone.') && (this.querySelector('button[type=submit]').disabled = true, true);">
                            @csrf
                            <div class="mb-3">
                                <label class="form-label">Refund amount</label>
                                <div class="input-group" style="max-width: 12rem;">
                                    <span class="input-group-text">$</span>
                                    <input type="number" step="0.01" min="0.01" max="{{ $order->refundable() }}" name="amount"
                                           value="{{ number_format($order->refundable(), 2, '.', '') }}" class="form-control" required>
                                </div>
                                <small class="form-hint">
                                    Up to ${{ number_format($order->refundable(), 2) }} can be returned. Lower it to keep a
                                    processing fee or to return one item of several. Refunded to the card that paid, on the
                                    {{ $order->stripeAccountLabel() }}.
                                </small>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Note <span class="text-muted">(optional, kept with the refund)</span></label>
                                <input type="text" name="note" class="form-control" maxlength="500" placeholder="e.g. Wrong size, reordered">
                            </div>
                            <div class="text-muted small mb-3">
                                The order's items are left as they are — they record what was charged. A fully refunded
                                order drops off the pick list.
                            </div>
                            <button type="submit" class="btn btn-danger">Refund</button>
                        </form>
                    </div>
                @elseif(! $canRefund && $order->refundable() > 0)
                    <div class="card mb-3">
                        <div class="card-body text-muted">
                            Refunds need the <code>store.issueRefunds</code> permission.
                        </div>
                    </div>
                @endif
            </div>
        </div>
    </div>
</div>
@endsection
