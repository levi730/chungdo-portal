{{-- Guests settings. Expects $addon in scope. --}}
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Max guests per person</label>
        <input type="number" min="0" step="1" class="form-control"
               name="settings[{{ $addon->type }}][max]"
               value="{{ $addon->setting('max', 0) }}">
        <small class="text-muted">0 disables guest sign-ups.</small>
    </div>
</div>
