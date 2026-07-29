@extends('layouts.dashboard')


@section('page-title')
    {{ $event->name }} - Registrations By Assigned Division
@endsection

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('content')

    <div >


    <table class="table table-bordered">
        @foreach($by_division as $divid=>$data)

            <tr>
                <th class="bg-primary text-white" colspan="4"><h2>{{ $data['division_name'] }}</h2></th>
                <th class="bg-primary text-white text-end">Total: {{ count($data['users']) }}</th>
            </tr>
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>School</th>
                <th>Rank</th>
            </tr>

            @foreach($data['users'] as $user)
                <tr>
                    <td>{{ $user->firstname }}</td>
                    <td><a href="mailto:{{ $user->email }}"> {{ $user->email }}</a></td>
                    <td>{{ $user->school->name }}</td>
                    <td>{{ $user->rank->rank }}</td>
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
@endsection
