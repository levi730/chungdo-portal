@extends('layouts.email')

@section('main')
    <table class="box" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content">
                            <h1><a href="{{ config('app.url') }}/project-united">Project United</a></h1>

                            <p>T-shirt Order Receipt:</p>

                            <table class="table">
                                <thead>
                                <tr>
                                    <th>Size</th>
                                    <th>Quantity</th>
                                    <th>Unit Price</th>
                                    <th>Line Total</th>
                                </tr>
                                </thead>
                                <tbody>
                                @php
                                    $items = $trans->metadata->raw_items;
                                    $total = 0;
                                     $upcharges = [
                                        'adult_2xl' => 2,
                                        'adult_3xl' => 3,
                                        'adult_4xl' => 4,
                                    ];
                                @endphp
                                @foreach ($items as $item)
                                    @php
                                        $label = ucwords(str_replace('_', ' ', $item['size']));
                                        $unit = 25 + ($upcharges[$item['size']] ?? 0);
                                        $lineTotal = $unit * $item['quantity'];
                                        $total += $lineTotal;
                                    @endphp
                                    <tr>
                                        <td>{{ $label }}</td>
                                        <td>{{ $item['quantity'] }}</td>
                                        <td>${{ number_format($unit, 2) }}</td>
                                        <td>${{ number_format($lineTotal, 2) }}</td>
                                    </tr>
                                @endforeach
                                </tbody>
                                <tfoot>
                                <tr>
                                    <td colspan="3">Total</td>
                                    <td>${{ number_format($total, 2) }}</td>
                                </tr>
                                </tfoot>
                            </table>

                            <p>Purchase Date: <b>{{ $trans->created_at->format('m/d/Y g:i a') }}</b></p>

                            <p>Thank you for your purchase, and keep training hard!</p>

                            <p>
                                {{ config('app.name') }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
@endsection
