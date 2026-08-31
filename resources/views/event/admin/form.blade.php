@extends('layouts.dashboard')

@section('page-title')
    {{ $creating ? 'Add Event' : 'Edit: '.$event->name }}
@endsection

@section('content')
<div class="container-xl">
    <div class="content">
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        {{-- Existing media with per-item delete (separate forms — can't nest in the main form) --}}
        @unless($creating)
            @if($event->getMedia('forms')->count() || $event->getMedia('slideshow-images')->count())
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title mb-0">Current files &amp; images</h3></div>
                    <div class="card-body">
                        @foreach($event->getMedia('forms') as $form)
                            <div class="d-flex align-items-center gap-2 mb-1">
                                <a href="{{ $form->getUrl() }}" target="_blank">{{ $form->name }}</a>
                                <form method="POST" action="{{ route('events.media.delete', [$event, $form->id]) }}" onsubmit="return confirm('Remove this file?')">
                                    @csrf @method('DELETE')
                                    <button class="btn btn-sm btn-outline-danger">Remove</button>
                                </form>
                            </div>
                        @endforeach
                        @include('partials.event.slideshow-focus')
                    </div>
                </div>
            @endif
        @endunless

        <form method="POST"
              action="{{ $creating ? route('events.store') : route('events.update', $event) }}"
              enctype="multipart/form-data">
            @csrf
            @unless($creating) @method('PUT') @endunless

            {{-- Core details --}}
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Details</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label required">Name</label>
                        <input type="text" name="name" class="form-control" value="{{ old('name', $event->name) }}" required>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Event type</label>
                            <select name="type" class="form-select">
                                <option value="">— unspecified —</option>
                                @foreach(\App\Models\Event::TYPES as $value => $label)
                                    <option value="{{ $value }}" @selected(old('type', $event->type) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <small class="form-hint">Competition types (Sparring/Forms/Combined) determine which registration cards print.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Hosted by</label>
                            <select name="host_school_id" class="form-select">
                                <option value="">Chung Do Association</option>
                                @foreach($schools as $school)
                                    <option value="{{ $school->id }}" @selected(old('host_school_id', $event->host_school_id) == $school->id)>{{ $school->name }}</option>
                                @endforeach
                            </select>
                            <small class="form-hint">Shown on the registration cards. Blank = Chung Do Association.</small>
                        </div>
                    </div>
                    <div class="row">
                        @php($stripeLocked = ! $creating && $event->hasPayments())
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payments go to</label>
                            <select name="stripe_account" class="form-select" @disabled($stripeLocked)>
                                @foreach($stripeAccounts->options() as $slug => $label)
                                    <option value="{{ $slug }}" @selected(old('stripe_account', $event->stripe_account ?? $stripeAccounts->default()) === $slug)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if($stripeLocked)
                                <input type="hidden" name="stripe_account" value="{{ $event->stripe_account }}">
                                <small class="form-hint text-warning">
                                    Locked — this event has taken payments. Refunds have to be issued on the
                                    account that took the charge, so it can't be moved.
                                </small>
                            @else
                                <small class="form-hint">
                                    Which Stripe account registration money lands in. Can't be changed once
                                    the first payment is taken.
                                </small>
                            @endif
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Starts</label>
                            <input type="datetime-local" name="startdatetime" class="form-control" value="{{ old('startdatetime', optional($event->startdatetime)->format('Y-m-d\TH:i')) }}">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Ends</label>
                            <input type="datetime-local" name="enddatetime" class="form-control" value="{{ old('enddatetime', optional($event->enddatetime)->format('Y-m-d\TH:i')) }}">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Location</label>
                        <textarea name="location" class="form-control" rows="2">{{ old('location', $event->location) }}</textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Details <span class="text-muted">(Markdown)</span></label>
                        <textarea name="details" class="form-control" rows="6">{{ old('details', $event->details) }}</textarea>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">URL slug</label>
                            <input type="text" name="slug" class="form-control" value="{{ old('slug', $event->slug) }}" placeholder="auto-generated from name">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Minimum rank</label>
                            <select name="minimum_rank_id" class="form-select">
                                <option value="">— none —</option>
                                @foreach($ranks as $rank)
                                    <option value="{{ $rank->id }}" @selected(old('minimum_rank_id', $event->minimum_rank_id) == $rank->id)>{{ $rank->rank }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Map embed URL <span class="text-muted">(Google Maps iframe src)</span></label>
                        <input type="text" name="map_url" class="form-control" value="{{ old('map_url', $event->map_url) }}">
                    </div>
                    <label class="form-check">
                        <input type="hidden" name="require_ticket" value="0">
                        <input type="checkbox" name="require_ticket" value="1" class="form-check-input" @checked(old('require_ticket', $event->require_ticket))>
                        <span class="form-check-label">Require a QR check-in ticket (mobile wallet passes)</span>
                    </label>

                    <label class="form-check">
                        <input type="hidden" name="highlighted" value="0">
                        <input type="checkbox" name="highlighted" value="1" class="form-check-input" @checked(old('highlighted', $event->highlighted))>
                        <span class="form-check-label">Feature this event on the home page</span>
                    </label>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Home page order</label>
                            <input type="number" name="highlight_order" class="form-control" min="0" max="65535"
                                   value="{{ old('highlight_order', $event->highlight_order ?? 0) }}">
                            <small class="form-hint">
                                Higher sits higher on the page. Leave at 0 unless you want this event
                                above another featured one. Featured events are pinned to the top of the
                                home page and the soonest upcoming events fill in below.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Media uploads --}}
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Add files &amp; images</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-label">Downloadable forms (PDF)</label>
                        <input type="file" name="forms[]" class="form-control" accept="application/pdf" multiple>
                    </div>
                    <div>
                        <label class="form-label">Slideshow images</label>
                        <input type="file" name="slideshow[]" class="form-control" accept="image/*" multiple>
                    </div>
                </div>
            </div>

            {{-- Add-ons (registration fee, meal, t-shirt, etc.) --}}
            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Registration &amp; add-ons</h3></div>
                <div class="card-body">
                    <p class="text-muted small">Enable the registration fee and any add-ons for this event. The <b>Registration Fee</b> should be enabled for a paid event.</p>
                    @include('event.addons._section', ['event' => $event])
                </div>
            </div>

            <div class="mb-4">
                <button type="submit" class="btn btn-primary">{{ $creating ? 'Create event' : 'Save changes' }}</button>
                <a href="{{ route('events.index') }}" class="btn btn-link">Cancel</a>
            </div>
        </form>
    </div>
</div>
@endsection
