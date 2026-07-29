@extends('layouts.dashboard')


@section('page-title')
    {{ $event->name }} - Registrations By Volunteer Selection
@endsection

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('content')
<div class="container-xl">
    <table class="table table-bordered">
        @foreach($grouped_data as $volunteer_selections=>$items)

            <tr>
                <th class="bg-primary text-white" colspan="4"><h2>{{ $volunteer_selections }}</h2></th>
                <th class="bg-primary text-white text-end" colspan="1">Total: {{ count($items) }}</th>
            </tr>
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Rank</th>
                <th>School</th>
            </tr>

            @foreach($items as $user)
                <tr>
                    <td>{{ $user->firstname }}</td>
                    <td>{{ $user->lastname }}</td>
                    <td><a href="mailto:{{ $user->email }}"> {{ $user->email }}</a></td>
                    <td>{{ $user->rank->rank }}</td>
                    <td>{{ $user->school->shortname }}</td>
                </tr>
            @endforeach

            @if(!$loop->last)
                <tr>
                    <td colspan="5">&nbsp;</td>
                </tr>
            @endif
        @endforeach

        <tr>

            <td colspan="5" class="text-end fs-2 fw-bold">Grand Total: {{ $total_count }}</td>
        </tr>
    </table>
</div>
@endsection
