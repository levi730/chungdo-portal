@extends('layouts.dashboard')

@section('extra-head')
    {{-- A public page of competitors' names, many of them children: keep it out of search results. --}}
    <meta name="robots" content="noindex, nofollow">
@endsection

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
