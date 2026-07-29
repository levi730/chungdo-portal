{{-- Potluck settings. Expects $addon in scope. Catalog rows are saved to
     potluck_options; the Category · Item editor shows when open sign-up is off. --}}
@php($potluckRows = app(\App\Services\PotluckCatalog::class)->rowsFor((int) $addon->event_id))

<div x-data="{
        rows: @js($potluckRows),
        openSignup: @js((bool) $addon->setting('open_signup', false)),
        get categories() { return [...new Set(this.rows.map(r => (r.category || '').trim()).filter(Boolean))].sort(); },
     }">
    {{-- Marker so the save routine knows the catalog editor was on the page. --}}
    <input type="hidden" name="potluck_catalog_present" value="1">

    <div class="mb-3">
        <label class="form-check">
            <input type="hidden" name="settings[{{ $addon->type }}][open_signup]" value="0">
            <input class="form-check-input" type="checkbox" value="1"
                   name="settings[{{ $addon->type }}][open_signup]" x-model="openSignup">
            <span class="form-check-label">Open sign-up (registrants type in their own dish)</span>
        </label>
        <div class="form-hint">When off, registrants pick from the catalog below.</div>
    </div>

    <div x-show="!openSignup" x-cloak>
        <label class="form-label">Potluck catalog</label>

        {{-- Autocomplete for the Category field, from categories already in use. --}}
        <datalist id="potluck-categories">
            <template x-for="c in categories" :key="c"><option :value="c"></option></template>
        </datalist>

        <template x-if="rows.length === 0">
            <div class="text-muted small mb-2">No items yet — add some below.</div>
        </template>

        <template x-for="(row, i) in rows" :key="i">
            <div class="row g-2 mb-2 align-items-center">
                <input type="hidden" :name="`potluck_catalog[${i}][id]`" :value="row.id">
                <div class="col-sm-5">
                    <input type="text" class="form-control" placeholder="Category (e.g. Dessert)"
                           list="potluck-categories" autocomplete="off"
                           :name="`potluck_catalog[${i}][category]`" x-model="row.category">
                </div>
                <div class="col-sm-5">
                    <input type="text" class="form-control" placeholder="Item (e.g. Brownies)"
                           :name="`potluck_catalog[${i}][item]`" x-model="row.item">
                </div>
                <div class="col-sm-2">
                    <template x-if="row.count > 0">
                        <span class="text-muted small" x-text="`${row.count} signed up`"></span>
                    </template>
                    <template x-if="row.count == 0">
                        <button type="button" class="btn btn-ghost-danger btn-icon"
                                @click="rows.splice(i, 1)" title="Remove item">&times;</button>
                    </template>
                </div>
            </div>
        </template>

        <button type="button" class="btn btn-sm btn-outline-primary mt-1"
                @click="rows.push({ id: 0, category: '', item: '', count: 0 })">+ Add item</button>
    </div>
</div>
