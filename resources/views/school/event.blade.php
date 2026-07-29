@extends('layouts.dashboard')

@section('page-title')
    {{ $school->name }} - {{ $event->name }}
@endsection

@section('content')
    <div class="content">


        <div class="container-xl">

            <table class="table table-bordered table-striped">
                <tr>
                    <th>First Name</th>
                    <th>Last Name</th>
                    <th>Rank</th>
                    <th>Sex</th>
                    <th>Age</th>
                    <th>Height</th>
                    <th>Weight</th>
                </tr>
                @foreach($registrants as $reg)
                    <tr>
                        <td>{{$reg->firstname}}</td>
                        <td>{{$reg->lastname}}</td>
                        <td>{{$reg->rank->rank}}</td>
                        <td>{{$reg->sex}}</td>
                        <td>{{$reg->age}}</td>
                        <td>{{$reg->height_text}}</td>
                        <td>{{$reg->weight}}</td>
                    </tr>
                @endforeach
		<tr>
		    <td colspan="7" class="text-end fs-2 fw-bold">Grand Total: {{ count($registrants) }}</td>
		</tr>
            </table>
        </div>

    </div>

@endsection

@push('js')

@endpush
