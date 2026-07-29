{{-- Add-on enable + config + deadline rows. Expects $event in scope. Used by the
     Manage Add-ons page and the event create/edit form. Posts enabled[type],
     settings[type][...], closes_at[type]. --}}
@php
    $addonRows = collect(\App\EventAddons\AddonRegistry::all())
        // Participation is automatic for Combined tournaments — not an admin toggle.
        ->filter(fn ($handler) => $handler->appliesTo($event) && $handler->type() !== 'participation')
        ->map(fn ($handler, $type) => [
            'handler' => $handler,
            'addon' => $event->addon($type) ?? new \App\Models\EventAddon([
                'event_id' => $event->id, 'type' => $type, 'enabled' => false, 'settings' => $handler->defaultSettings(),
            ]),
        ]);
@endphp

@foreach($addonRows as $row)
    @php($handler = $row['handler'])
    @php($addon = $row['addon'])
    <div class="card mb-3">
        <div class="card-header">
            <label class="form-check form-switch mb-0">
                <input class="form-check-input" type="checkbox" name="enabled[{{ $handler->type() }}]" value="1" @checked($addon->enabled)>
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
                    <small class="text-muted">After this time the add-on stops accepting sign-ups and changes.</small>
                </div>
            </div>
        </div>
    </div>
@endforeach
