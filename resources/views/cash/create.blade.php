@extends('layouts.dashboard')

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('page-title')
    {{ $event->name }} &mdash; Register students paying cash/check
@endsection

@section('content')
<div class="container-xl">
    <div class="d-flex align-items-center mb-3">
        <a href="{{ route('event.register', $event->slug) }}" class="btn btn-link px-0">&larr; Back to {{ $event->name }}</a>
        <a href="{{ route('cash.remittances') }}" class="btn btn-link ms-auto">My cash/check remittances</a>
    </div>
    <p class="text-secondary">
        For students who paid you in cash or by check. They are registered right away; you get a slip to put in the
        envelope with the money, and the registration shows as unpaid until the money is received.
    </p>
    <livewire:event.cash-registration :event-id="$event->id" />
</div>
@endsection
