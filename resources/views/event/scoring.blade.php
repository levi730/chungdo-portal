@extends('layouts.dashboard')

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('page-title')
    {{ $event->name }}
@endsection

@section('content')
    <div class="content">
        <livewire:event.scoring :event-id="$event->id" />
    </div>
@endsection
