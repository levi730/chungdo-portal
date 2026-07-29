@extends('layouts.blank')


@section('page-title')
    {{ $event->name }} - Registrations By Assigned Division
@endsection

@section('content')

    <div class="container">
        <div class="row">
            @foreach($by_division as $divid=>$data)
                <div class="col-sm-6">
                    <div class="bg-primary text-white">
                        <h2 class="mb-0 ">{{ $data['division_name'] }}</h2>
                    </div>
                    <div class="container">
                        @foreach($data['users'] as $user)
                            <div class="row w-full">
                                <div class="col">{{ $user->firstname }} {{ $user->lastname }}</div>
                                <div class="col">{{ $user->school->name }}</div>
                                <div class="col">{{ $user->rank->rank }}</div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </div>
    </div>

@endsection
