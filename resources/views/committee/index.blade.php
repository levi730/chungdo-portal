@extends('layouts.dashboard')

@section('page-title')
    Committees
@endsection

@section('content')
<div class="container-xl">
    <p class="text-secondary">
        The association's committees, what each one is responsible for, and who serves on it.
        Contact details are here for members only — please keep them inside the association.
    </p>

    @forelse($committees as $committee)
        <div class="card mb-4">
            <div class="card-header">
                <div>
                    <h2 class="card-title mb-0">
                        <a href="{{ route('committees.show', $committee->slug) }}" class="text-reset">{{ $committee->name }}</a>
                    </h2>
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
    @empty
        <div class="empty">
            <p class="empty-title">No committees yet</p>
            <p class="empty-subtitle text-secondary">
                Committees are set up under Admin &rarr; Committees.
            </p>
        </div>
    @endforelse
</div>
@endsection
