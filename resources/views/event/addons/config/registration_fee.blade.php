{{-- Registration fee settings. Expects $addon in scope. --}}
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Cost per person</label>
        <div class="input-group input-group-flat">
            <span class="input-group-text">$</span>
            <input type="number" min="0" step="0.01" class="form-control"
                   name="settings[{{ $addon->type }}][cost]"
                   value="{{ $addon->setting('cost', 0) }}">
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Cost label</label>
        <input type="text" class="form-control" placeholder="per person"
               name="settings[{{ $addon->type }}][cost_type]"
               value="{{ $addon->setting('cost_type', 'per person') }}">
    </div>
</div>
<div class="row">
    <div class="col-12"><label class="form-label">Household discount (amount off each additional person)</label></div>
    @foreach(['2' => '2nd', '3' => '3rd', '4' => '4th', '5' => '5th+'] as $tier => $labelText)
        <div class="col-md-3 mb-2">
            <label class="form-label text-muted small">{{ $labelText }} person</label>
            <div class="input-group input-group-flat">
                <span class="input-group-text">$</span>
                <input type="number" min="0" step="0.01" class="form-control"
                       name="settings[{{ $addon->type }}][discounts][{{ $tier }}]"
                       value="{{ $addon->setting('discounts', [])[$tier] ?? 0 }}">
            </div>
        </div>
    @endforeach
</div>
