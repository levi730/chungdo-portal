@extends('layouts.dashboard')

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('page-title')
    {{ $event->name }} Participation Trophies
@endsection

@section('content')
    <style>
        @media print {
            .navbar, .no-print, footer { display: none !important; }
            .container-xl { max-width: 100% !important; }
            .card { break-inside: avoid; }
        }
    </style>
    <div class="content">
        <livewire:event.participation-trophies :event-id="$event->id" />
    </div>
@endsection
