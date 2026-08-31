<table>
    <thead>
    <tr>
        <th colspan="3"><b>Stripe account: {{ $stripeAccount }}</b></th>
    </tr>
    <tr>
        <th><b>Date</b></th>
        <th><b>Type</b></th>
        <th><b>Payor</b></th>
        <th><b>Email</b></th>
        <th><b>Registrants</b></th>
        @foreach($columns as $label)
            <th><b>{{ $label }}</b></th>
        @endforeach
        <th><b>Total</b></th>
        <th><b>Stripe Reference</b></th>
    </tr>
    </thead>
    <tbody>
    @foreach($rows as $row)
    <tr>
        <td>@if($row['date']){{ \PhpOffice\PhpSpreadsheet\Shared\Date::dateTimeToExcel($row['date']) }}@endif</td>
        <td>{{ $row['type'] }}</td>
        <td>{{ $row['payor'] }}</td>
        <td>{{ $row['email'] }}</td>
        <td>{{ $row['registrants'] }}</td>
        @foreach($columns as $type => $label)
            <td>@if(array_key_exists($type, $row['amounts'])){{ $row['amounts'][$type] }}@endif</td>
        @endforeach
        <td>{{ $row['total'] }}</td>
        <td>{{ $row['stripe_ref'] }}</td>
    </tr>
    @endforeach
    <tr>
        <td></td>
        <td><b>Net Collected</b></td>
        <td></td>
        <td></td>
        <td></td>
        @foreach($columns as $label)
            <td></td>
        @endforeach
        <td><b>{{ $net }}</b></td>
        <td></td>
    </tr>
    </tbody>
</table>
