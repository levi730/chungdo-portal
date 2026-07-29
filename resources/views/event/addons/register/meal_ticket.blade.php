{{-- Meal ticket cell: header label + attending toggle + additional count.
     Expects $user, $addon in scope and an Alpine `meals` map keyed by user id. --}}
<div class="addon-label">
    {{ $addon->setting('label', 'Meal') }}@if($addon->setting('price', 0) > 0) (${{ number_format($addon->setting('price', 0), 2) }} ea)@endif
</div>
<label class="form-check form-switch mb-0">
    <input class="form-check-input" type="checkbox" x-model="meals[{{ $user->id }}].attending">
    <span class="form-check-label">Attending</span>
</label>
<div x-show="meals[{{ $user->id }}].attending" x-cloak class="mt-1">
    <label class="form-label mb-0 small text-muted">Add'l people</label>
    <div class="input-group input-group-sm" style="width: 7.5rem;">
        <button class="btn btn-outline-secondary px-2" type="button"
                @click="meals[{{ $user->id }}].additional = Math.max(0, (parseInt(meals[{{ $user->id }}].additional) || 0) - 1)">&minus;</button>
        <input type="number" min="0" step="1" x-model.number="meals[{{ $user->id }}].additional"
               class="form-control text-center px-1 qty-input">
        <button class="btn btn-outline-secondary px-2" type="button"
                @click="meals[{{ $user->id }}].additional = (parseInt(meals[{{ $user->id }}].additional) || 0) + 1">+</button>
    </div>
</div>
