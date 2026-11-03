<table>
    <thead>
    <tr>
        <th colspan="3"><b>{{ config('store.name') }} financials: {{ $scope }}</b></th>
    </tr>
    <tr>
        <th><b>Date</b></th>
        <th><b>Type</b></th>
        <th><b>Buyer</b></th>
        <th><b>Email</b></th>
        <th><b>Pickup school</b></th>
        <th><b>Stripe account</b></th>
        @foreach($columns as $label)
            <th><b>{{ $label }}</b></th>
        @endforeach
        <th><b>Total</b></th>
        <th><b>Order</b></th>
        <th><b>Stripe reference</b></th>
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $row)
    <tr>
        <td>@if($row['date']){{ \PhpOffice\PhpSpreadsheet\Shared\Date::dateTimeToExcel($row['date']) }}@endif</td>
        <td>{{ $row['type'] }}</td>
        <td>{{ $row['buyer'] }}</td>
        <td>{{ $row['email'] }}</td>
        <td>{{ $row['school'] }}</td>
        <td>{{ $row['account'] }}</td>
        @foreach($columns as $productId => $label)
            <td>@if(array_key_exists($productId, $row['amounts'])){{ $row['amounts'][$productId] }}@endif</td>
        @endforeach
        <td>{{ $row['total'] }}</td>
        <td>{{ $row['order'] }}</td>
        <td>{{ $row['reference'] }}</td>
    </tr>
    @endforeach
    <tr>
        <td></td>
        <td><b>Net Collected</b></td>
        <td></td>
        <td></td>
        <td></td>
        <td></td>
        @foreach($columns as $label)
            <td></td>
        @endforeach
        <td><b>{{ $net }}</b></td>
        <td></td>
        <td></td>
    </tr>
    @foreach($netByAccount as $account => $amount)
        <tr>
            <td></td>
            <td>In {{ $account }}</td>
            <td></td>
            <td></td>
            <td></td>
            <td></td>
            @foreach($columns as $label)
                <td></td>
            @endforeach
            <td>{{ $amount }}</td>
            <td></td>
            <td></td>
        </tr>
    @endforeach
    </tbody>
</table>
