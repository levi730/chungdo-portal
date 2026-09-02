@extends('layouts.dashboard')

@section('page-title')
    All Schools
@endsection

@section('content')
<div class="container-xl">
    <div class="content">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <div class="d-flex justify-content-between align-items-center mb-3">
            <div class="text-secondary">
                {{ $all_schools->count() }} {{ Str::plural('school', $all_schools->count()) }}
            </div>
            <div class="d-flex gap-2">
                {{-- The shareable version. Staff need to find the link to hand out. --}}
                <a href="{{ route('schools.public') }}" class="btn btn-outline-secondary" target="_blank" rel="noopener">
                    Public directory
                </a>
                @can('create', App\Models\School::class)
                    <a href="{{ route('school.create') }}" class="btn btn-primary">Add school</a>
                @endcan
            </div>
        </div>

        <div class="row row-cards">
            @forelse($all_schools as $school)
                <div class="col-md-6 col-xl-4">
                    @include('school.partials.card', ['school' => $school, 'manageable' => true])
                </div>
            @empty
                <div class="col-12">
                    <div class="card">
                        <div class="card-body text-secondary">No schools yet.</div>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
</div>
@endsection
