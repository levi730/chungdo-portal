@extends('layouts.dashboard')


@section('page-title')
    {{ $event->name }} - Registrants by T-Shirt Size
@endsection

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('content')
    <table class="table table-bordered">
        @foreach($size_order as $size=>$size_text)
            @if($grouped_data->has($size))
                <tr>
                    <th class="bg-primary text-white" colspan="5"><h2>{{ $size }}</h2></th>
                    <th class="bg-primary text-white text-end" colspan="4">Total: {{ count($grouped_data[$size]) }}</th>
                </tr>
                <tr>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Email</th>
                    <th>Rank</th>
                    <th>Age</th>
                    <th>Sex</th>
                    <th>Height</th>
                    <th>Weight</th>
                    <th>T-Shirt Size</th>
                </tr>

                @foreach($grouped_data[$size] as $user)
                    <tr>
                        <td>{{ $user->firstname }}</td>
                        <td>{{ $user->lastname }}</td>
                        <td><a href="mailto:{{ $user->email }}"> {{ $user->email }}</a></td>
                        <td>{{ $user->rank->rank }}</td>
                        <td>{{ $user->age }}</td>
                        <td>{{ $user->sex }}</td>
                        <td>{{ $user->height_text }}</td>
                        <td>{{ $user->weight }}</td>
                        <td>{{ $user->school->shortname }}</td>
                    </tr>
                @endforeach

                @if(!$loop->last)
                    <tr>
                        <td colspan="9">&nbsp;</td>
                    </tr>
                @endif
            @endif
        @endforeach


        <tr>

            <td colspan="8" class="text-end fs-2 fw-bold">Grand Total: {{ $total_count }}</td>
        </tr>
    </table>
@endsection
