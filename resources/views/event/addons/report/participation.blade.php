@extends('layouts.dashboard')

@section('page-title')
    {{ $event->name }} - Participation
@endsection

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('content')
    @php
        use App\EventAddons\EventParticipationAddon;
        $sparring = $answers->filter(fn ($a) => in_array($a->value, [EventParticipationAddon::SPARRING, EventParticipationAddon::BOTH], true))->count();
        $forms = $answers->filter(fn ($a) => in_array($a->value, [EventParticipationAddon::FORMS, EventParticipationAddon::BOTH], true))->count();
        $both = $answers->where('value', EventParticipationAddon::BOTH)->count();
    @endphp

    <div class="row row-cards mb-3">
        <div class="col">
            <div class="card"><div class="card-body">
                <div class="subheader">Sparring competitors</div>
                <div class="h1">{{ $sparring }}</div>
            </div></div>
        </div>
        <div class="col">
            <div class="card"><div class="card-body">
                <div class="subheader">Forms competitors</div>
                <div class="h1">{{ $forms }}</div>
            </div></div>
        </div>
        <div class="col">
            <div class="card"><div class="card-body">
                <div class="subheader">Doing both</div>
                <div class="h1">{{ $both }}</div>
            </div></div>
        </div>
        <div class="col">
            <div class="card"><div class="card-body">
                <div class="subheader">Total registered</div>
                <div class="h1">{{ $answers->count() }}</div>
            </div></div>
        </div>
    </div>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>School</th>
                <th>Competing In</th>
            </tr>
        </thead>
        <tbody>
            @forelse($answers->sortBy(fn ($a) => optional($a->registration->user)->lastname) as $answer)
                @php($user = $answer->registration->user)
                <tr>
                    <td>{{ $user->firstname }}</td>
                    <td>{{ $user->lastname }}</td>
                    <td>{{ $user->school->shortname ?? '' }}</td>
                    <td>{{ EventParticipationAddon::choiceLabel($answer->value) }}</td>
                </tr>
            @empty
                <tr><td colspan="4" class="text-center text-muted">No registrations yet.</td></tr>
            @endforelse
        </tbody>
    </table>
@endsection
