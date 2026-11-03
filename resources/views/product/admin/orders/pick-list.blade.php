@extends('layouts.dashboard')

@section('page-title')
    Pick list: {{ $product->name }} — {{ $run->name }}
@endsection

@section('content')
<style>
    @media print {
        .navbar, .page-header, .no-print, footer { display: none !important; }
        .container-xl { max-width: 100% !important; }
        .card { border: 1px solid #000 !important; box-shadow: none !important; break-inside: avoid; }
        .school-block { break-before: page; }
        .school-block:first-of-type { break-before: auto; }
        .collect-box { display: inline-block; width: 1.1em; height: 1.1em; border: 1px solid #000; vertical-align: middle; }
    }
    @media screen {
        .collect-box { display: none; }
    }
</style>
<div class="container-xl">
    <div class="content">
        <div class="d-flex flex-wrap align-items-center gap-2 mb-3 no-print">
            <a href="{{ route('products.runs.edit', [$product, $run]) }}" class="btn btn-link px-0">&larr; {{ $run->name }}</a>
            <div class="ms-auto d-flex gap-2">
                <a href="{{ route('orders.financials', ['run' => $run->id]) }}" class="btn btn-outline-secondary">Export financials</a>
                @if($order_count > $collected_count)
                    <form method="POST" action="{{ route('products.runs.mark-ready', [$product, $run]) }}"
                          onsubmit="return confirm('Mark every order on this run ready for pickup?')">
                        @csrf
                        <button class="btn btn-outline-primary">Mark all ready for pickup</button>
                    </form>
                @endif
                <button type="button" class="btn btn-primary" onclick="window.print()">Print</button>
            </div>
        </div>

        @if(session('success'))
            <div class="alert alert-success no-print">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger no-print">{{ session('error') }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-end mb-3">
            <div>
                <div class="text-secondary text-uppercase small">Pick list</div>
                <h1 class="mb-0">{{ $product->name }} — {{ $run->name }}</h1>
                <div class="text-muted">
                    {{ $order_count }} paid {{ str('order')->plural($order_count) }},
                    {{ $collected_count }} collected.
                    @if($run->expected_arrival_at) Expected {{ $run->expected_arrival_at->format('M j, Y') }}. @endif
                    @if($run->pickup_note) {{ $run->pickup_note }} @endif
                </div>
            </div>
            <div class="text-muted small">Printed {{ now()->format('M j, Y g:ia') }}</div>
        </div>

        @if($order_count === 0)
            <div class="card"><div class="card-body text-muted">No paid orders on this run yet.</div></div>
        @else
            <div class="card mb-4">
                <div class="card-header"><h3 class="card-title mb-0">Whole run — what to order</h3></div>
                <div class="table-responsive">
                    <table class="table table-sm card-table">
                        <thead><tr><th>Variant</th><th class="text-end">Quantity</th></tr></thead>
                        <tbody>
                            @foreach($totals as $t)
                                <tr><td>{{ $t['variant'] }}</td><td class="text-end">{{ $t['quantity'] }}</td></tr>
                            @endforeach
                            <tr class="fw-bold"><td>Total items</td><td class="text-end">{{ array_sum(array_column($totals, 'quantity')) }}</td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            @foreach($schools as $block)
                <div class="card mb-4 school-block">
                    <div class="card-header">
                        <h3 class="card-title mb-0">
                            {{ $block['school']?->name ?? 'No pickup school chosen' }}
                            <span class="text-muted fw-normal ms-2">{{ count($block['orders']) }} {{ str('order')->plural(count($block['orders'])) }}</span>
                        </h3>
                    </div>
                    <div class="card-body pb-0">
                        <div class="text-secondary text-uppercase small mb-1">In this school's box</div>
                        <div class="d-flex flex-wrap gap-3 mb-3">
                            @foreach($block['totals'] as $t)
                                <span><strong>{{ $t['quantity'] }}</strong> &times; {{ $t['variant'] }}</span>
                            @endforeach
                        </div>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-vcenter card-table">
                            <thead>
                                <tr>
                                    <th style="width: 7rem;">Collected</th>
                                    <th>Name</th>
                                    <th>Items</th>
                                    <th class="no-print">Contact</th>
                                    <th class="text-end no-print">Order</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($block['orders'] as $row)
                                    @php($order = $row['order'])
                                    <tr @class(['text-muted' => $order->isCollected()])>
                                        <td>
                                            <span class="collect-box">@if($order->isCollected())&#10003;@endif</span>
                                            <form method="POST" action="{{ route('orders.collected', $order) }}" class="no-print d-inline">
                                                @csrf
                                                <input type="hidden" name="collected" value="{{ $order->isCollected() ? 0 : 1 }}">
                                                <label class="form-check mb-0">
                                                    <input type="checkbox" class="form-check-input" onchange="this.form.submit()" @checked($order->isCollected())>
                                                    <span class="form-check-label small">{{ $order->isCollected() ? 'Yes' : 'No' }}</span>
                                                </label>
                                            </form>
                                        </td>
                                        <td>
                                            <strong>{{ $order->name }}</strong>
                                            @if((float) $order->refunded_amount > 0)
                                                <span class="badge bg-red-lt ms-1" title="Part of this order was refunded; check the order before handing everything over.">part refunded</span>
                                            @endif
                                        </td>
                                        <td>
                                            @foreach($row['lines'] as $line)
                                                <div>{{ $line->quantity }} &times; {{ $line->variant_name ?: $line->product_name }}</div>
                                            @endforeach
                                            @if($row['other_lines'] > 0)
                                                <div class="text-muted small">+ {{ $row['other_lines'] }} {{ str('item')->plural($row['other_lines']) }} from another run</div>
                                            @endif
                                        </td>
                                        <td class="no-print small">{{ $order->email }}@if($order->phone)<br>{{ $order->phone }}@endif</td>
                                        <td class="text-end no-print"><a href="{{ route('orders.show', $order) }}">{{ $order->shortReference() }}</a></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
            @endforeach
        @endif
    </div>
</div>
@endsection
