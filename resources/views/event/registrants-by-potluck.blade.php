@extends('layouts.dashboard')


@section('page-title')
    {{ $event->name }} - Registrations By Potluck Selection
@endsection

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('content')

<div class="container-xl">
    <table class="table table-bordered">
        @foreach($grouped_data as $potluck_category=>$top)
            @if($potluck_category != 'None')
                <tr class="mt-4">
                    <th class="bg-primary text-white" colspan="4"><h2>{{ (trim($potluck_category)) ? $potluck_category : "None" }}</h2></th>
                    <th class="bg-primary text-white text-end" colspan="2">Total: {{ $top['count']  }}</th>
                </tr>

                @foreach($top['records'] as $itemname => $sub)
                    @if(!$event->addon('potluck')?->setting('open_signup'))
                    <tr>
                        <th class="bg-secondary text-white" colspan="4"><h3>{{ (trim($itemname)) ? $itemname : "None" }}</h3></th>
                        <th class="bg-secondary text-white text-end" colspan="2">Total: {{ $sub['count'] }}</th>
                    </tr>
                    @endif
                    <tr>
                        <th>First Name</th>
                        <th>Last Name</th>
                        <th>Email</th>
                        <th>Rank</th>
                        <th>School</th>
                        <th>Item</th>
                    </tr>

                    @if(count($sub['records']) > 0)
                        @forelse($sub['records'] as $user)
                            <tr>
                                <td>{{ $user->firstname }}</td>
                                <td>
                                    {{ $user->lastname }}
                                    @if($user->other_registrations->count() > 0)
                                        (<a href="#" onclick="return false;" data-bs-toggle="tooltip" data-bs-placement="top" title="{{ $user->other_registrations_string }}">{{$user->other_registrations->count()}}</a>)
                                    @endif
                                </td>
                                <td><a href="mailto:{{ $user->email }}"> {{ $user->email }}</a></td>
                                <td>{{ $user->rank?->rank }}</td>
                                <td>{{ $user->school?->shortname }}</td>
                                <td>{{ $user->pivot->potluckOpenItem() }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5">N/A</td>
                            </tr>
                       @endforelse
                    @endif
                @endforeach

                @if(!$loop->last)
                    <tr>
                        <td colspan="6">&nbsp;</td>
                    </tr>
                @endif
            @else

                <tr>
                    <th class="bg-primary text-white" colspan="4"><h2>None</h2></th>
                    <th class="bg-primary text-white text-end" colspan="2">Total: {{ $top['count'] }}</th>
                </tr>
                @foreach($top['records'] as $user)
                    <tr>
                        <td>{{ $user->firstname }}</td>
                        <td>{{ $user->lastname }}</td>
                        <td><a href="mailto:{{ $user->email }}"> {{ $user->email }}</a></td>
                        <td>{{ $user->rank?->rank }}</td>
                        <td>{{ $user->school?->shortname }}</td>
                    </tr>
                @endforeach

            @endif
        @endforeach

        <tr>

            <td colspan="5" class="text-end fs-2 fw-bold">Grand Total: {{ $total_count }}</td>
        </tr>
    </table>
</div>
@endsection
