{{--
    One school in the directory. Shared by the members' index and the public
    page so the two can't drift apart.

    $manageable — show View/Edit/Restore controls. False on the public page,
    which has no controls at all and never sees an archived school.
--}}
@php($manageable = $manageable ?? false)
@php($photo = $school->photo())

<div class="card h-100 @if($school->trashed()) opacity-75 @endif">

    @if($photo)
        <img src="{{ glideCropUrlFromMedia($photo, 600, 400) }}"
             alt="{{ $school->name }}" class="card-img-top"
             style="height:150px;object-fit:cover;">
    @else
        {{-- No photo: the short name in a tinted panel. Deliberate, rather than
             the broken template avatar that used to sit here. --}}
        <div class="d-flex align-items-center justify-content-center bg-primary-lt"
             style="height:150px;border-radius:4px 4px 0 0;">
            <span class="h1 mb-0 text-primary">{{ $school->shortname ?: Str::substr($school->name, 0, 3) }}</span>
        </div>
    @endif

    <div class="card-body">
        @if($school->trashed())
            <span class="badge bg-secondary text-white mb-2">Archived</span>
        @endif

        <h3 class="card-title mb-1">{{ $school->name }}</h3>

        @if($school->principal_instructors_text)
            <div class="text-secondary mb-2">{{ $school->principal_instructors_text }}</div>
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

    @if($manageable)
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
                @if(in_array($school->id, $editable_school_ids ?? []))
                    <a href="{{ route('school.edit', $school->id) }}" class="card-btn flex-fill">Edit</a>
                @endif
            </div>
        @endif
    @endif
</div>
