{{-- T-shirt cell: header label + size select. Expects $user in scope and an
     Alpine `tshirts` map keyed by user id. --}}
<div class="addon-label">T-Shirt Size</div>
<select x-model="tshirts[{{ $user->id }}]" class="form-select form-select-sm" style="max-width: 11rem;">
    <option value=""> -- Select --</option>
    <optgroup label="Adult Sizes">
        <option value="S">Adult S</option>
        <option value="M">Adult M</option>
        <option value="L">Adult L</option>
        <option value="XL">Adult XL</option>
        <option value="2XL">Adult 2XL</option>
        <option value="3XL">Adult 3XL</option>
    </optgroup>
    <optgroup label="Youth Sizes">
        <option value="YXS">Youth XS</option>
        <option value="YS">Youth S</option>
        <option value="YM">Youth M</option>
        <option value="YL">Youth L</option>
    </optgroup>
</select>
