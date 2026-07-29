{{-- Competition participation cell: which events this student competes in.
     Expects $user in scope and an Alpine `participation` map keyed by user id.
     Defaults to "both" (set by the controller's prefill). --}}
<div class="addon-label">Competing In</div>
<select x-model="participation[{{ $user->id }}]" class="form-select form-select-sm" style="max-width: 12rem;">
    <option value="both">Sparring + Forms</option>
    <option value="sparring">Sparring only</option>
    <option value="forms">Forms only</option>
</select>
