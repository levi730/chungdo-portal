@extends('layouts.dashboard')


@section('page-title')
    {{ $event->name }} - Registration by School
@endsection

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('content')
<div class="container-xl">
    <table>
    @foreach($grouped_data as $school=>$items)
            <tr>
                <th colspan="7"><h2>{{ $school }}</h2></th>
            </tr>
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Rank</th>
                <th>Age</th>
                <th>Gender</th>
                <th>Height</th>
                <th>Weight</th>
            </tr>

            @foreach($items as $user)
                <tr>
                    <td>{{ $user->firstname }}</td>
                    <td>{{ $user->lastname }}</td>
                    <td><a href="mailto:{{ $user->email }}"> {{ $user->email }}</a></td>
                    <td>{{ $user->rank->rank }}</td>
                    <td>{{ $user->age }}</td>
                    <td>{{ $user->gender }}</td>
                    <td>{{ $user->height_text }}</td>
                    <td>{{ $user->weight }}</td>
                </tr>
            @endforeach
    @endforeach
    </table>
</div>
@endsection
