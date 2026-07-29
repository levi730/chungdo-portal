@extends('layouts.dashboard')

@section('page-title')
    {{ $event->name }} - {{ $addon->setting('label', 'Meal') }} Tickets
@endsection

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('content')
    @php
        $price = (float) $addon->setting('price', 0);
        $registrantMeals = $answers->where('selected', true)->count();
        $additionalMeals = (int) $answers->sum('quantity');
        $totalMeals = $registrantMeals + $additionalMeals;
        $totalRevenue = (float) $answers->sum('amount');
    @endphp

    <div class="row row-cards mb-3">
        <div class="col">
            <div class="card"><div class="card-body">
                <div class="subheader">Registrants attending</div>
                <div class="h1">{{ $registrantMeals }}</div>
            </div></div>
        </div>
        <div class="col">
            <div class="card"><div class="card-body">
                <div class="subheader">Additional guests</div>
                <div class="h1">{{ $additionalMeals }}</div>
            </div></div>
        </div>
        <div class="col">
            <div class="card"><div class="card-body">
                <div class="subheader">Total meals</div>
                <div class="h1">{{ $totalMeals }}</div>
            </div></div>
        </div>
        <div class="col">
            <div class="card"><div class="card-body">
                <div class="subheader">Total collected</div>
                <div class="h1">${{ number_format($totalRevenue, 2) }}</div>
            </div></div>
        </div>
    </div>

    <table class="table table-bordered">
        <thead>
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>School</th>
                <th>Attending</th>
                <th>Additional</th>
                <th>Meals</th>
                <th>Amount</th>
            </tr>
        </thead>
        <tbody>
            @forelse($answers->sortByDesc('quantity') as $answer)
                @php($user = $answer->registration->user)
                @php($meals = ($answer->selected ? 1 : 0) + (int) $answer->quantity)
                <tr>
                    <td>{{ $user->firstname }}</td>
                    <td>{{ $user->lastname }}</td>
                    <td>{{ $user->school->shortname ?? '' }}</td>
                    <td>{{ $answer->selected ? 'Yes' : 'No' }}</td>
                    <td>{{ (int) $answer->quantity }}</td>
                    <td>{{ $meals }}</td>
                    <td>${{ number_format((float) $answer->amount, 2) }}</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted">No meal tickets yet.</td></tr>
            @endforelse
        </tbody>
        <tfoot>
            <tr class="fw-bold">
                <td colspan="5" class="text-end">Grand Total:</td>
                <td>{{ $totalMeals }}</td>
                <td>${{ number_format($totalRevenue, 2) }}</td>
            </tr>
        </tfoot>
    </table>
@endsection
