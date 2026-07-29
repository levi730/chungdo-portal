@extends('layouts.dashboard')


@section('page-title')
    {{ $event->name }} - All Registrations
@endsection

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('content')

    <div x-data="{
        sort: '{{ $sort }}',
        changeSort() {
            var url = new URL(location.href);
            var search_params = url.searchParams;

            search_params.set('sort', this.sort);
            url.search = search_params.toString();
            var new_url = url.toString();
            location.href = new_url;
        }
     }">


    <div class="container">
        <!--Row with two equal columns-->
        <div class="row">
            <div class="col-md-6 bg-azure text-nowrap">
                <div class="input-group mb-3">
                    <span class="input-group-text" id="basic-addon1">Sort By: </span>
                    <select x-model="sort" class="form-select" aria-label="Sort by" @change="changeSort()">
                        <option value="ras">Rank - Age - Sex</option>
                        <option value="rsa">Rank - Sex - Age</option>
                        <option value="ars">Age - Rank - Sex</option>
                        <option value="asr">Age - Sex - Rank</option>
                        <option value="sar">Sex - Age - Rank</option>
                        <option value="sra">Sex - Rank - Age</option>
                    </select>
                </div>
            </div>
            <div class="col-md-3 bg-red">&nbsp;</div>
            <div class="col-md-3 bg-cyan text-right"><button type="button" class="btn btn-success">Excel</button></div>
        </div>
    </div>


    <table class="table table-bordered">
        @foreach($grouped_data as $div=>$items)

            <tr>
                <th class="bg-primary text-white" colspan="5"><h2>{{ $div }}</h2></th>
                <th class="bg-primary text-white text-end" colspan="4">Total: {{ count($items) }}</th>
            </tr>
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>School</th>
                <th>Rank</th>
                <th>Age</th>
                <th>Sex</th>
                <th>Height</th>
                <th>Weight</th>
            </tr>

            @foreach($items as $user)
                <tr>
                    <td>{{ $user->firstname }}</td>
                    <td>{{ $user->lastname }}</td>
                    <td><a href="mailto:{{ $user->email }}"> {{ $user->email }}</a></td>
                    <td>{{ $user->school->name }}</td>
                    <td>{{ $user->rank->rank }}</td>
                    <td>{{ $user->age }}</td>
                    <td>{{ $user->sex }}</td>
                    <td>{{ $user->height_text }}</td>
                    <td>{{ $user->weight }}</td>
                </tr>
            @endforeach

            @if(!$loop->last)
                <tr>
                    <td colspan="9">&nbsp;</td>
                </tr>
            @endif
        @endforeach

        <tr>

            <td colspan="9" class="text-end fs-2 fw-bold">Grand Total: {{ $total_count }}</td>
        </tr>
    </table>
@endsection
