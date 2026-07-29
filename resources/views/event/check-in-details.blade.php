@extends('layouts.dashboard')

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('page-title')
    {{ $event->name }} — Check In
@endsection

@section('content')
    <div class="content">
        <div class="container-xl">
            <livewire:event.check-in-details :slug="$slug" :users="$users" />
        </div>
    </div>
@endsection
