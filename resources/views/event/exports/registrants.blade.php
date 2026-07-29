<table>
    <thead>
    <tr>
        <th><b>First Name</b></th>
        <th><b>Last Name</b></th>
        <th><b>Email</b></th>
        <th><b>Rank</b></th>
        <th><b>DOB</b></th>
        <th><b>School</b></th>
        <th><b>Potluck Item</b></th>
        <th><b>Volunteer Selections</b></th>
        <th><b>Signup Time</b></th>
        <th><b>T-Shirt Size</b></th>
        <th><b>Guests</b></th>
    </tr>
    </thead>
    <tbody>
    @foreach($registrations as $reg)
    <tr>
        <td>{{ $reg->firstname }}</td>
        <td>{{ $reg->lastname }}</td>
        <td>{{ $reg->email }}</td>
        <td>{{ $reg->rank?->rank }}</td>
        <td>@if($reg->dob){{ \PhpOffice\PhpSpreadsheet\Shared\Date::dateTimeToExcel($reg->dob) }}@endif</td>
        <td>{{ $reg->school?->shortname }}</td>
        <td>{{ $reg->pivot->potluckLabel() }}</td>
        <td>{{ collect($reg->pivot->volunteerSelections())->implode(',') }}</td>
        <td>{{ \PhpOffice\PhpSpreadsheet\Shared\Date::dateTimeToExcel($reg->pivot->created_at) }}</td>
        <td>{{ $reg->pivot->tshirtSize() }}</td>
        <td>{{ $reg->pivot->guestCount() }}</td>
    </tr>
    @endforeach
    </tbody>
</table>
