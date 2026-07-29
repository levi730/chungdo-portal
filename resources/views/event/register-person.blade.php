{{-- One student: header (name/school/rank + register toggle) and, when being
     registered, their per-student add-ons as a delineated responsive grid that
     "drops down" beneath the header. --}}
@php($isRegistered = $user->events()->find($event->id))
<div class="card card-sm mb-2">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-start">
            <div>
                <span class="fw-bold" style="font-size: 1.15rem;">{{ $user->full_name }}</span>
                @if($user->school)
                    <span class="text-muted small ms-2 d-none d-sm-inline">{{ $user->school->name }} ({{ $user->school->city }}, {{ $user->school->state }})</span>
                @endif
                @if($user->is_student)
                    <div class="mt-1">
                        <select x-model="ranks[{{ $user->id }}]" x-bind:disabled="!selectedForReg({{ $user->id }})"
                                class="form-select form-select-sm" style="max-width: 12rem;">
                            <option value=""> -- Belt Rank --</option>
                            @foreach($allRanks as $rank)
                                <option value="{{ $rank->id }}">{{ $rank->rank }}</option>
                            @endforeach
                        </select>
                    </div>
                @endif
            </div>
            <div class="text-end ps-2">
                @if($isRegistered)
                    <span class="badge bg-success text-white">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="16" height="16" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M5 12l5 5l10 -10"></path></svg>
                        Registered
                    </span>
                @elseif($user->can_register_for_tournaments)
                    <label class="form-check form-switch mb-0">
                        <input class="form-check-input" type="checkbox" name="register" value="{{ $user->id }}"
                               x-model="registered"
                               :class="registered.includes('{{ $user->id }}') ? 'bg-primary border-primary' : 'bg-secondary border-secondary'">
                    </label>
                @else
                    <span class="badge bg-danger text-white" title="School, rank, height and weight are all required">Missing data</span>
                    <div class="small"><a href="{{ route('profile.edit') }}">Update profile</a></div>
                @endif
            </div>
        </div>

        @if($isRegistered)
            @php($reg = ($registeredRegs ?? collect())->get($user->id))
            @php($editCells = $event->enabledAddons()->filter(fn ($a) => $a->isOpen() && $a->handler() && $a->handler()->scope() === \App\EventAddons\AddonHandler::SCOPE_PER_STUDENT && $a->handler()->registrantView()))

            {{-- View mode: current add-on badges + an Edit button --}}
            <div class="mt-2 pt-2 border-top d-flex flex-wrap align-items-center gap-1" x-show="!editing['{{ $user->id }}']">
                @if($reg && $reg->addonAnswers->count())
                    @foreach($reg->addonAnswers as $answer)
                        @php($label = \App\EventAddons\AddonRegistry::for($answer->type)?->badgeLabel($answer))
                        @if($label)
                            <span class="badge bg-azure-lt text-azure">{{ $label }}</span>
                        @endif
                    @endforeach
                @else
                    <span class="text-muted small">No add-ons selected.</span>
                @endif
                @if(($canEditAddons ?? false) && $editCells->count())
                    <button type="button" class="btn btn-sm btn-outline-primary ms-1" @click="startEdit('{{ $user->id }}')">Edit add-ons</button>
                @endif
            </div>

            {{-- Edit mode: editable, pre-filled controls + a live delta --}}
            @if(($canEditAddons ?? false) && $editCells->count())
                <div x-show="editing['{{ $user->id }}']" x-cloak class="mt-2 pt-2 border-top">
                    <div class="addon-grid">
                        @foreach($editCells as $addon)
                            <div class="addon-cell">
                                @include($addon->handler()->registrantView(), ['user' => $user, 'addon' => $addon])
                            </div>
                        @endforeach
                    </div>
                    <div class="mt-2 d-flex flex-wrap align-items-center gap-2">
                        <span class="small text-primary" x-show="adjustDelta('{{ $user->id }}') > 0">
                            Adds $<span x-text="adjustDelta('{{ $user->id }}').toFixed(2)"></span> &mdash; you'll enter a card next.
                        </span>
                        <span class="small text-warning" x-show="adjustDelta('{{ $user->id }}') < 0">
                            Refund of $<span x-text="Math.abs(adjustDelta('{{ $user->id }}')).toFixed(2)"></span> &mdash; requires admin approval.
                        </span>
                        <button type="button" class="btn btn-sm btn-primary" @click="saveEdit('{{ $user->id }}')" :disabled="adjustSaving">
                            Save changes <span x-show="adjustSaving" class="spinner-border spinner-border-sm ms-1"></span>
                        </button>
                        <button type="button" class="btn btn-sm btn-link" @click="cancelEdit('{{ $user->id }}')">Cancel</button>
                    </div>
                    <div class="text-red small mt-1" x-show="adjustError" x-text="adjustError"></div>
                </div>
            @endif
        @elseif($user->can_register_for_tournaments)
            @php($cells = $event->enabledAddons()->filter(fn ($a) => $a->isOpen() && $a->handler() && $a->handler()->scope() === \App\EventAddons\AddonHandler::SCOPE_PER_STUDENT && $a->handler()->registrantView()))
            @if($cells->count())
                <div x-show="selectedForReg({{ $user->id }})" x-cloak class="addon-grid mt-2 border-top">
                    @foreach($cells as $addon)
                        <div class="addon-cell">
                            @include($addon->handler()->registrantView(), ['user' => $user, 'addon' => $addon])
                        </div>
                    @endforeach
                </div>
            @endif
        @endif
    </div>
</div>
