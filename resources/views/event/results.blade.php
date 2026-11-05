@extends('layouts.dashboard')

@section('extra-head')
    {{-- Competitors are mostly children: keep these pages out of search results. --}}
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
