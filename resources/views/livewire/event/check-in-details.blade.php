<div>
    @if($flash)
        <div class="alert alert-{{ $flash['type'] }} d-flex align-items-center" role="alert">
            <div>{{ $flash['msg'] }}</div>
        </div>
    @endif

    @forelse($forms as $i => $f)
        <div class="card mb-3" wire:key="checkin-{{ $f['id'] }}">
            <div class="card-header bg-primary text-light d-flex justify-content-between align-items-center">
                <h3 class="card-title mb-0">{{ $f['full_name'] }}</h3>
                <div>
                    @if($f['registered'] && ! $f['checked_in'])
                        <button type="button" wire:click="checkIn({{ $i }})" wire:loading.attr="disabled" class="btn btn-white text-primary btn-sm">
                            &#10003; Save &amp; Check In
                        </button>
                    @endif
                    <button type="button" wire:click="skip({{ $f['id'] }})" class="btn btn-white text-danger btn-sm">Skip</button>
                </div>
            </div>

            @if($f['registered'] && ! $f['checked_in'])
                <div class="card-body row"
                     x-data="{
                        height: {{ (int) ($f['height'] ?? 0) }},
                        dob: '{{ $f['dob'] }}',
                        get feetIn() { return Math.floor(this.height / 12) + &quot;' &quot; + (this.height % 12) + '&quot;'; },
                        get age() {
                            if (! this.dob) return '';
                            const d = new Date(this.dob + 'T00:00:00'), n = new Date();
                            let a = n.getFullYear() - d.getFullYear();
                            if (n.getMonth() < d.getMonth() || (n.getMonth() === d.getMonth() && n.getDate() < d.getDate())) a--;
                            return a;
                        }
                     }">
                    <div class="col-md-6 mb-3">
                        <label class="form-label">First name</label>
                        <input type="text" class="form-control @error('forms.'.$i.'.firstname') is-invalid @enderror" wire:model="forms.{{ $i }}.firstname" autocomplete="off">
                        @error('forms.'.$i.'.firstname') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Last name</label>
                        <input type="text" class="form-control @error('forms.'.$i.'.lastname') is-invalid @enderror" wire:model="forms.{{ $i }}.lastname" autocomplete="off">
                        @error('forms.'.$i.'.lastname') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Rank</label>
                        <select class="form-select @error('forms.'.$i.'.rank_id') is-invalid @enderror" wire:model="forms.{{ $i }}.rank_id">
                            <option value="">--</option>
                            @foreach($ranks as $rank)
                                <option value="{{ $rank->id }}">{{ $rank->rank }}</option>
                            @endforeach
                        </select>
                        @error('forms.'.$i.'.rank_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">School</label>
                        <select class="form-select @error('forms.'.$i.'.school_id') is-invalid @enderror" wire:model="forms.{{ $i }}.school_id">
                            <option value="">--</option>
                            @foreach($schools as $school)
                                <option value="{{ $school->id }}">{{ $school->shortname }}</option>
                            @endforeach
                        </select>
                        @error('forms.'.$i.'.school_id') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Date of Birth</label>
                        <input type="date" class="form-control @error('forms.'.$i.'.dob') is-invalid @enderror" wire:model="forms.{{ $i }}.dob" x-on:input="dob = $event.target.value">
                        @error('forms.'.$i.'.dob') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3 mb-3 d-flex align-items-end">
                        <span>Age <b x-text="age"></b></span>
                    </div>

                    <div class="col-md-3 mb-3">
                        <label class="form-label">Height (inches)</label>
                        <input type="number" class="form-control @error('forms.'.$i.'.height') is-invalid @enderror" wire:model="forms.{{ $i }}.height" x-on:input="height = +$event.target.value">
                        @error('forms.'.$i.'.height') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-3 mb-3 d-flex align-items-end">
                        <span><b x-text="feetIn"></b></span>
                    </div>

                    <div class="col-md-6 mb-3">
                        <label class="form-label">Weight (lb)</label>
                        <input type="number" class="form-control @error('forms.'.$i.'.weight') is-invalid @enderror" wire:model="forms.{{ $i }}.weight">
                        @error('forms.'.$i.'.weight') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="form-label">Sex</label>
                        <select class="form-select @error('forms.'.$i.'.sex') is-invalid @enderror" wire:model="forms.{{ $i }}.sex">
                            <option value="">--</option>
                            <option value="F">Female</option>
                            <option value="M">Male</option>
                        </select>
                        @error('forms.'.$i.'.sex') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>

                    <div class="col-12 fs-3">
                        <b>T-Shirt: <span class="text-red">{{ $f['tshirt'] ?: 'N/A' }}</span></b>
                    </div>
                </div>
            @elseif($f['checked_in'])
                <div class="card-body">
                    <div class="alert alert-success mb-0">{{ $f['full_name'] }} is already checked in!</div>
                </div>
            @else
                <div class="card-body">
                    <div class="alert alert-danger mb-0">{{ $f['full_name'] }} is not registered for this event.</div>
                </div>
            @endif
        </div>
    @empty
        <div class="text-end mb-3">
            <a class="btn btn-primary" href="{{ route('event.check-in', $slug) }}">&laquo; Back to check-in list</a>
        </div>
        <div class="alert alert-info">No more registrants to check in.</div>
    @endforelse

    @if(count($forms))
        <div class="mt-2">
            <a class="btn btn-link" href="{{ route('event.check-in', $slug) }}">&laquo; Back to list</a>
        </div>
    @endif
</div>
