@extends('layouts.dashboard')

@section('page-title')
    {{ $committee->name }}
@endsection

@section('content')
<div class="container-xl">
    <div class="mb-3">
        <a href="{{ route('committees.index') }}" class="text-secondary">&larr; All committees</a>
    </div>

    <div class="card">
        <div class="card-header">
            <div>
                <h2 class="card-title mb-0">{{ $committee->name }}</h2>
                <div class="text-secondary">
                    {{ $committee->members->count() }} member{{ $committee->members->count() === 1 ? '' : 's' }}
                </div>
            </div>
        </div>
        <div class="card-body">
            @if($committee->description)
                <p>{{ $committee->description }}</p>
            @endif
            <div class="row g-3">
                @include('partials.committee.members', ['members' => $committee->members])
            </div>
        </div>
    </div>
</div>
@endsection
