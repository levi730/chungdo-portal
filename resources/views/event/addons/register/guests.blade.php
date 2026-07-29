{{-- Guests cell: header label + stepper. Expects $user, $addon in scope and an
     Alpine `guests` map keyed by user id. --}}
@php($max = (int) $addon->setting('max', 0))
<div class="addon-label">Guests</div>
<div class="input-group input-group-sm" style="width: 7.5rem;">
    <button class="btn btn-outline-secondary px-2" type="button"
            @click="guests[{{ $user->id }}] = Math.max(0, (parseInt(guests[{{ $user->id }}]) || 0) - 1)">&minus;</button>
    <input type="number" min="0" max="{{ $max }}" step="1"
           x-model.number="guests[{{ $user->id }}]" class="form-control text-center px-1 qty-input">
    <button class="btn btn-outline-secondary px-2" type="button"
            @click="guests[{{ $user->id }}] = Math.min({{ $max }}, (parseInt(guests[{{ $user->id }}]) || 0) + 1)">+</button>
</div>
