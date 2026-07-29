
@if (session('avatar-success-main'))
    <div class="alert alert-success">{{ session('avatar-success-main') }}</div>
@endif

<div class="row mb-3 avatar-anchor">
    <div class="col-sm-3 text-center m-auto">
                                <span
                                    style="--tblr-avatar-size: 8rem; background-image: url(@if (Auth::user()->avatar) {{ asset('storage/avatars/'.Auth::user()->avatar) }} @else '/img/default_avatar.png' @endif)"
                                    class="avatar avatar-xl"></span>
    </div>
    <div class="col-sm-9">
        <form enctype="multipart/form-data" action="{{ route('profile.avatar') }}"
              method="POST" id="updateAvatarMain">
            @csrf
            <div class="form-group mb-3">
                <div class="form-label">Upload your own Profile Picture</div>
                <input type="file" name="avatar" class="form-control"
                       accept=".png,.jpg,.jpeg,.gif,.webp">
                <small class="form-hint">We recommend uploading a image with a min width of
                    200px and a max width of 1000px. Only png, jpg, gif and webp files are
                    allowed.</small>
            </div>
            <input type="submit" class="btn btn-primary">
            <button class="btn btn-danger"
                    onclick="event.preventDefault(); document.getElementById('deleteAvatarMain').submit()">Delete Profile
                Picture</button>

        </form>
    </div>
    <form action="{{ route('profile.deleteavatar') }}" method="post" id="deleteAvatarMain">
        @csrf
        @method('DELETE')
    </form>

</div>

<hr>

@if ($errors->updateProfileInformation->any())
    <div class="alert alert-danger">
        <h4 class="alert-title">Error!</h4>
        <ul>
            @foreach ($errors->updateProfileInformation->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

@if (session('tab-profile-success'))
    <div class="alert alert-success">{{ session('tab-profile-success') }}</div>
@endif

@php
    $is_student_val = old('is_student') ?? auth()->user()->is_student;
@endphp

<form method="POST" name="mainForm" action="{{ route('user-profile-information.update') }}" x-data="{is_student: Boolean({{$is_student_val}}) }">
    @csrf
    @method('PUT')

    <div class="row">
        <div class="col-sm mb-3">
            <label class="form-label">First Name</label>
            <input type="text" name="firstname" class="form-control bg-white"
                   value="{{ old('firstname') ?? auth()->user()->firstname }}" required autofocus
                   autocomplete="none" />
        </div>
        <div class="col-sm mb-3">
            <label class="form-label">Last Name</label>
            <input type="text" name="lastname" class="form-control bg-white"
                   value="{{ old('lastname') ?? auth()->user()->lastname }}" required
                   autocomplete="none" />
        </div>
    </div>

    <div class="row">
        <div class="col-sm mb-3">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control"
                   value="{{ old('email') ?? auth()->user()->email }}" required autocomplete="none"/>
        </div>
    </div>


    <div class="row">
        <div class="col-sm mb-3">
            <label class="form-label">Street Address</label>
            <input type="text" name="address1" class="form-control"
                   value="{{ old('address1') ?? auth()->user()->address1 }}" autocomplete="none" />
        </div>
    </div>


    <div class="row">
        <div class="col-sm mb-3">
            <label class="form-label">Street Address 2</label>
            <input type="text" name="address2" class="form-control"
                   value="{{ old('address2') ?? auth()->user()->address2 }}" autocomplete="none" />
        </div>
    </div>


    <div class="row">
        <div class="col-sm-7 mb-3">
            <label class="form-label">City</label>
            <input type="text" name="city" class="form-control"
                   value="{{ old('city') ?? auth()->user()->city }}" autocomplete="none" />
        </div>
        <div class="col-sm-2 mb-3">
            <label class="form-label">State</label>
            <input type="text" name="state" class="form-control" maxlength="2"
                   value="{{ old('state') ?? auth()->user()->state }}" autocomplete="none" />
        </div>
        <div class="col-sm-3 mb-3">
            <label class="form-label">Zip</label>
            <input type="text" name="zip" class="form-control"
                   value="{{ old('zip') ?? auth()->user()->zip }}"  autocomplete="none" />
        </div>
    </div>


    <div class="mb-3 mt-5">
        <label class="form-check form-switch">
            <input class="form-check-input" type="checkbox" name="is_student" x-model="is_student">
            <span class="form-check-label">Are you currently a student?</span>
        </label>
    </div>

    <div x-show="is_student" x-transition>
        <x-student-edit-form :user="auth()->user()" />
    </div>


    <div>
        <button type="submit" class="btn btn-primary">
            Update Profile
        </button>
    </div>
</form>



@push('js')
    <script src="https://unpkg.com/axios/dist/axios.min.js"></script>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            in2fi(this.mainForm);
        });

        function in2fi(frm) {

            let h = frm['height'];
            let feet = frm.height_ft;
            let inches = frm.height_in;
            console.log(h.value);
            if(h.value < 1) {
                feet.value = '';
                inches.value = '';
                return false;
            }

            let feet_val = Math.floor(h.value / 12);
            let in_val = h.value % 12;


            if(!isNaN(feet_val) && !isNaN(in_val)) {
                feet.value =    feet_val;
                inches.value = in_val;
            } else {
                feet.value = '';
                inches.value = '';
            }

        }

        function fi2in(frm) {
            let h = frm['height'];
            let feet = parseInt(frm.height_ft.value);
            let inches = parseInt(frm.height_in.value);

            if(!isNaN(feet) && isNaN(inches)) {
                h.value = parseInt(feet*12); // + 0
            } else if(!isNaN(feet) && !isNaN(inches)) {
                h.value = parseInt(feet * 12) + parseInt(inches);
            } else {
                h.value = null;
            }

        }




    </script>
@endpush
