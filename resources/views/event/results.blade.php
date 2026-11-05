@extends('layouts.dashboard')

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('page-title')
    {{ $event->name }} Results
@endsection

@section('content')
    <div class="content">
        <livewire:event.results :event-id="$event->id" />
    </div>
@endsection
