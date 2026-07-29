{{-- Volunteer settings. Expects $addon in scope. One role per line. --}}
<div class="mb-3">
    <label class="form-label">Volunteer roles <span class="text-muted">(one per line)</span></label>
    <textarea class="form-control" rows="4"
              name="settings[{{ $addon->type }}][options]">{{ implode("\n", $addon->setting('options', [])) }}</textarea>
</div>
