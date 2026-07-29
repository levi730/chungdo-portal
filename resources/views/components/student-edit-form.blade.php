<div>



    <div class="row">
        <div class="col-sm-8 mb-3 ">
            <label class="form-label">School</label>
            <div>
                <select class="form-select" name="school_id" required>
                    <option value=""> -- Choose --</option>
                    @php $sel_id = old('school_id') ?? $user->school_id; @endphp
                    @foreach($schools as $school)
                        <option {{ $school->id == $sel_id ? 'selected' : '' }} value="{{ $school->id }}">{{ $school->name }} ({{ $school->city }}, {{ $school->state }})</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="col-sm-4 mb-3 ">
            <label class="form-label">Rank</label>
            <div>
                <select class="form-select" name="rank_id" required>
                    <option value=""> -- Choose --</option>
                    @php $sel_id = old('rank_id') ?? $user->rank_id; @endphp
                    @foreach($ranks as $rank)
                        <option {{ $rank->id == $sel_id ? 'selected' : '' }} value="{{ $rank->id }}">{{ $rank->rank }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </div>


    <div class="row">
        <div class="col-sm mb-3">
            <label class="form-label">Date of birth</label>

            @php
                $compareval = old('dob');
                if(!$compareval) {
                    $compareval = $user->dob;
                }
                if(!$compareval) {
                    $dateval = null;
                } else {
                    $dateval = (new Carbon\Carbon($compareval))->format('m/d/Y');
                }
            @endphp

            <x-date-picker
                id="datepicker_{{$user->id}}"
                pik-trigger-id="piktrigger-{{$user->id}}"
                autocomplete="none"
                name="dob"
                value="{{ $dateval }}"
            />


        </div>

        <div class="col-sm mb-3">
            <label class="form-label">Height</label>
            <div class="input-group mb-2">
                <input type="text" class="form-control" id="height_ft_{{$user->id}}" name="height_ft" autocomplete="none" onchange="fi2in(this.form);" min="0" max="9">
                <span class="input-group-text px-1">
                  ft.
                </span>
                <input type="text" class="form-control" id="height_in_{{$user->id}}" name="height_in" autocomplete="none" onchange="fi2in(this.form);" min="0" max="11">
                <span class="input-group-text px-1">
                  in.
                </span>
            </div>
            <input type="hidden" name="height" id="height_{{$user->id}}" value="{{ old('height') ?? $user->height }}">
        </div>

        <div class="col-sm mb-3">
            <label class="form-label">Weight</label>
            <div class="input-group mb-2">
                <input type="text" class="form-control" name="weight" autocomplete="none" value="{{ old('weight') ?? $user->weight }}">
                <span class="input-group-text">
                      lbs.
                    </span>
            </div>
        </div>

        <div class="col-sm mb-3">
            <label class="form-label">Sex</label>
            <div class="input-group mb-2">
                <select class="form-select" name="sex" required>
                    <option value=""> -- Choose --</option>
                    @php $sel_id = old('sex') ?? $user->sex; @endphp
                    <option {{ 'F' == $sel_id ? 'selected' : '' }} value="F">Female</option>
                    <option {{ 'M' == $sel_id ? 'selected' : '' }} value="M">Male</option>    
                </select>
            </div>
        </div>

    </div>
    <div class="mb-3 -mt-1">
        <small class="form-hint text-warning">
            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-alert-triangle" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M12 9v2m0 4v.01"></path><path d="M5 19h14a2 2 0 0 0 1.84 -2.75l-7.1 -12.25a2 2 0 0 0 -3.5 0l-7.1 12.25a2 2 0 0 0 1.75 2.75"></path></svg>
            Date of Birth, Height, and Weight are all required to register for tournaments!
        </small>
    </div>


</div>
