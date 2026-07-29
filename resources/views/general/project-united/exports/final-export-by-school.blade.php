<table>
    <thead>
    <tr>
        <th><b>Type</b></th>
        <th><b>School</b></th>
        <th><b>Person</b></th>
        <th><b>Adult XS</b></th>
        <th><b>Adult S</b></th>
        <th><b>Adult M</b></th>
        <th><b>Adult L</b></th>
        <th><b>Adult XL</b></th>
        <th><b>Adult 2XL</b></th>
        <th><b>Adult 3XL</b></th>
        <th><b>Adult 4XL</b></th>
        <th><b>Kids S</b></th>
        <th><b>Kids M</b></th>
        <th><b>Kids L</b></th>
        <th><b>Kids XL</b></th>
        <th><b>Total</b></th>
    </tr>
    </thead>
    <tbody>
    @foreach($to_schools_data as $rec)
        <tr>
            <td>
                @if($rec['type'] == 'project_united_tshirt')
                    T-Shirt
                @elseif($rec['type'] == 'project_united_hoodie')
                    Hoodie
                @else
                    ERROR: {{ $rec['type'] }}
                @endif
            </td>
            <td>{{ $rec['school'] }}</td>
            <td>{{ $rec['person'] }}</td>
            <td>{{ $rec['adult_xs'] }}</td>
            <td>{{ $rec['adult_s'] }}</td>
            <td>{{ $rec['adult_m'] }}</td>
            <td>{{ $rec['adult_l'] }}</td>
            <td>{{ $rec['adult_xl'] }}</td>
            <td>{{ $rec['adult_2xl'] }}</td>
            <td>{{ $rec['adult_3xl'] }}</td>
            <td>{{ $rec['adult_4xl'] }}</td>
            <td>{{ $rec['kids_s'] }}</td>
            <td>{{ $rec['kids_m'] }}</td>
            <td>{{ $rec['kids_l'] }}</td>
            <td>{{ $rec['kids_xl'] }}</td>
            <td>{{ $rec['total'] }}</td>
        </tr>
    @endforeach
    </tbody>
</table>
