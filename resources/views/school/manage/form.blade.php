@extends('layouts.dashboard')

@section('page-title')
    {{ $creating ? 'Add a school' : 'Edit: '.$school->name }}
@endsection

@section('content')
<div class="container-xl">
    <div class="content">
        <div class="mb-3">
            <a href="{{ route('school.index') }}" class="btn btn-link px-0">&larr; All schools</a>
        </div>

        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        @if(! $creating && $school->trashed())
            <div class="alert alert-warning">
                This school is archived, so it won't appear in lists or be offered as a pickup
                location. Restore it from the <a href="{{ route('school.index') }}">school list</a>.
            </div>
        @endif

        {{-- The photo sits outside the main form: removing it is its own POST,
             and HTML can't nest forms. Same shape as the event and product
             media blocks. --}}
        @unless($creating)
            @php($photo = $school->photo())
            @if($photo)
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title mb-0">Current photo</h3></div>
                    <div class="card-body d-flex align-items-center gap-3">
                        <img src="{{ glideCropUrlFromMedia($photo, 400, 260) }}" alt="{{ $school->name }}"
                             style="width:200px;height:130px;object-fit:cover;border-radius:4px;">
                        <form method="POST" action="{{ route('school.photo.delete', $school->id) }}"
                              onsubmit="return confirm('Remove this photo?')">
                            @csrf @method('DELETE')
                            <button class="btn btn-outline-danger btn-sm">Remove photo</button>
                        </form>
                    </div>
                </div>
            @endif
        @endunless

        <form method="POST" enctype="multipart/form-data"
              action="{{ $creating ? route('school.store') : route('school.update', $school->id) }}">
            @csrf
            @unless($creating) @method('PUT') @endunless

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Details</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label required">Name</label>
                            <input type="text" name="name" class="form-control" required
                                   value="{{ old('name', $school->name) }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Short name</label>
                            <input type="text" name="shortname" class="form-control"
                                   value="{{ old('shortname', $school->shortname) }}">
                            <small class="form-hint">Used where space is tight, like the member list.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Where it is</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Address</label>
                        <input type="text" name="address1" class="form-control mb-2"
                               value="{{ old('address1', $school->address1) }}" placeholder="Street address">
                        <input type="text" name="address2" class="form-control"
                               value="{{ old('address2', $school->address2) }}" placeholder="Suite, unit (optional)">
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">City</label>
                            <input type="text" name="city" class="form-control" value="{{ old('city', $school->city) }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">State</label>
                            <input type="text" name="state" class="form-control" value="{{ old('state', $school->state) }}">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">ZIP</label>
                            <input type="text" name="zip" class="form-control" value="{{ old('zip', $school->zip) }}">
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Contact</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Phone</label>
                            <input type="text" name="phone" class="form-control" value="{{ old('phone', $school->phone) }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" name="email" class="form-control" value="{{ old('email', $school->email) }}">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Website</label>
                            <input type="text" name="url" class="form-control" value="{{ old('url', $school->url) }}"
                                   placeholder="example.com">
                            <small class="form-hint">https:// is added if you leave it off.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Photo</h3></div>
                <div class="card-body">
                    <label class="form-label">{{ ($creating || ! $school->photo()) ? 'Add a photo' : 'Replace the photo' }}</label>
                    <input type="file" name="photo" class="form-control" accept="image/*">
                    <small class="form-hint">
                        Shown on the school directory. One per school — uploading a new one
                        replaces the old. It's cropped wide, so a storefront, a class or a
                        group shot works better than a portrait.
                    </small>
                </div>
            </div>

            <div class="mb-4">
                <button type="submit" class="btn btn-primary">{{ $creating ? 'Add school' : 'Save changes' }}</button>
                <a href="{{ route('school.index') }}" class="btn btn-link">Cancel</a>
            </div>
        </form>

        {{-- Archiving is its own form, so it can't be nested in the one above. --}}
        @unless($creating)
            @can('delete', $school)
                @unless($school->trashed())
                    <form method="POST" action="{{ route('school.destroy', $school->id) }}"
                          onsubmit="return confirm('Archive {{ $school->name }}? Its members and records are kept, and it can be restored.')">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm">Archive this school</button>
                    </form>
                @endunless
            @endcan
        @endunless
    </div>
</div>
@endsection
