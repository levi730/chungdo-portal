{{-- Meal ticket settings. Expects $addon in scope. Field names are namespaced
     by add-on type so the manage form can save every add-on at once. --}}
<div class="row">
    <div class="col-md-4 mb-3">
        <label class="form-label">Price per meal</label>
        <div class="input-group input-group-flat">
            <span class="input-group-text">$</span>
            <input type="number" min="0" step="0.01" class="form-control"
                   name="settings[{{ $addon->type }}][price]"
                   value="{{ $addon->setting('price', 0) }}">
        </div>
    </div>
    <div class="col-md-4 mb-3">
        <label class="form-label">Label</label>
        <input type="text" class="form-control" placeholder="Meal"
               name="settings[{{ $addon->type }}][label]"
               value="{{ $addon->setting('label', 'Meal') }}">
    </div>
</div>
<div class="row">
    <div class="col-12 mb-3">
        <label class="form-label">Menu <span class="text-muted">(optional, shown on the printable meal ticket)</span></label>
        <textarea class="form-control" rows="6"
                  placeholder="# Choose one entree&#10;- Grilled chicken plate&#10;- Pulled pork sandwich&#10;- Veggie stir-fry&#10;&#10;Includes a side and a drink."
                  name="settings[{{ $addon->type }}][description]">{{ $addon->setting('description', '') }}</textarea>
        <small class="form-hint">Markdown-ish: lines starting with <code>#</code> are headings, <code>-</code> or <code>*</code> become bullets.</small>
    </div>
</div>
