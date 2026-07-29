@extends('layouts.dashboard')

@section('extra-head')
    <!-- Extra head -->
    @inertiaHead
@endsection

@section('content')
    @inertia
@endsection

@pushonce('js')
    @vite('resources/js/app.js')
@endpushonce
