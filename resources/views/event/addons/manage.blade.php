@extends('layouts.dashboard')

@section('page-title')
    {{ $event->name }} - Manage Add-ons
@endsection

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('content')
<div class="container-xl">
    <div class="content">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <p class="text-muted">
            Turn add-ons on or off for this event and configure their settings.
            Enabled add-ons appear on the registration form and in registrant reports.
        </p>

        <form method="POST" action="{{ route('event.addons-save', $event->slug) }}">
            @csrf

            @foreach($rows as $row)
                @php($handler = $row['handler'])
                @php($addon = $row['addon'])
                <div class="card mb-3">
                    <div class="card-header">
                        <label class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox"
                                   name="enabled[{{ $handler->type() }}]" value="1"
                                   @checked($addon->enabled)>
                            <span class="form-check-label h3 mb-0">{{ $handler->label() }}</span>
                        </label>
                    </div>
                    <div class="card-body">
                        @if($handler->configView())
                            @include($handler->configView(), ['addon' => $addon])
                        @endif

                        <div class="row">
                            <div class="col-md-6 mb-1">
                                <label class="form-label">Sign-ups close at <span class="text-muted">(optional)</span></label>
                                <input type="datetime-local" class="form-control" style="max-width: 22rem;"
                                       name="closes_at[{{ $handler->type() }}]"
                                       value="{{ optional($addon->closes_at)->format('Y-m-d\TH:i') }}">
                                <small class="text-muted">After this time the add-on stops accepting sign-ups and changes. Leave blank for no deadline.</small>
                            </div>
                        </div>
                    </div>
                </div>
            @endforeach

            <button class="btn btn-primary" type="submit">Save Add-ons</button>
        </form>

    </div>
</div>
@endsection
