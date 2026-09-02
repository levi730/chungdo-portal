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
            @can('create', App\Models\School::class)
                <a href="{{ route('school.create') }}" class="btn btn-primary">Add school</a>
            @endcan
        </div>

        <div class="row row-cards">
            @forelse($all_schools as $school)
                <div class="col-md-6 col-xl-4">
                    <div class="card h-100 @if($school->trashed()) opacity-75 @endif">

                        {{-- Photo slot. Schools have no images yet, so this is
                             the short name set in a tinted panel — deliberate
                             rather than the broken avatar that used to sit here
                             (public/static/avatars/010m.jpg has never existed).
                             When photos arrive this becomes an <img>. --}}
                        <div class="d-flex align-items-center justify-content-center bg-primary-lt"
                             style="height:110px;border-radius:4px 4px 0 0;">
                            <span class="h1 mb-0 text-primary">{{ $school->shortname ?: Str::substr($school->name, 0, 3) }}</span>
                        </div>

                        <div class="card-body">
                            @if($school->trashed())
                                <span class="badge bg-secondary text-white mb-2">Archived</span>
                            @endif

                            <h3 class="card-title mb-1">{{ $school->name }}</h3>

                            @if($school->principal_instructors_text)
                                <div class="text-secondary mb-2">
                                    {{ $school->principal_instructors_text }}
                                </div>
                            @endif

                            @if($school->address1 || $school->city)
                                <div class="text-secondary small mb-2">
                                    @if($school->address1){{ $school->address1 }}<br>@endif
                                    @if($school->address2){{ $school->address2 }}<br>@endif
                                    @if($school->city){{ $school->city }}@if($school->state), {{ $school->state }}@endif {{ $school->zip }}@endif
                                </div>
                            @endif

                            <div class="small">
                                @if($school->phone)
                                    <div><a href="tel:{{ preg_replace('/[^0-9+]/', '', $school->phone) }}">{{ $school->phone }}</a></div>
                                @endif
                                @if($school->email)
                                    <div><a href="mailto:{{ $school->email }}">{{ $school->email }}</a></div>
                                @endif
                                @if($school->url)
                                    <div>
                                        <a href="{{ $school->url }}" target="_blank" rel="noopener">
                                            {{ Str::of($school->url)->replaceFirst('https://', '')->replaceFirst('http://', '')->rtrim('/') }}
                                        </a>
                                    </div>
                                @endif
                            </div>
                        </div>

                        @if($school->trashed())
                            @can('restore', $school)
                                <form method="POST" action="{{ route('school.restore', $school->id) }}">
                                    @csrf
                                    <button class="card-btn w-100 border-0 bg-transparent">Restore</button>
                                </form>
                            @endcan
                        @else
                            <div class="d-flex">
                                <a href="{{ route('school.view', $school->id) }}" class="card-btn flex-fill">View</a>
                                @if(in_array($school->id, $editable_school_ids))
                                    <a href="{{ route('school.edit', $school->id) }}" class="card-btn flex-fill">Edit</a>
                                @endif
                            </div>
                        @endif
                    </div>
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
