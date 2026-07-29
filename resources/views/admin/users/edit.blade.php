@extends('layouts.dashboard')

@section('page-title')
    Edit User — {{ $user->full_name }}
@endsection

@section('content')
<div class="content">
    <div class="container-xl">

        <div class="d-flex align-items-center mb-3">
            <a href="{{ route('admin.users.index') }}" class="btn btn-link px-0">&larr; Back to users</a>
            @unless ($user->is(auth()->user()))
                <a href="{{ route('user.impersonate', $user) }}" class="btn btn-outline-secondary ms-auto"
                   onclick="return confirm('Log in as {{ $user->full_name }}? You can return via the leave-impersonation control.');">
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon me-1" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M13 12v.01"/><path d="M3 21h18"/><path d="M5 21v-16a2 2 0 0 1 2 -2h7.5m2.5 10.5v7.5"/><path d="M14 7h7m-3 -3l3 3l-3 3"/></svg>
                    Impersonate
                </a>
            @endunless
        </div>

        @if (session('admin-user-success'))
            <div class="alert alert-success">{{ session('admin-user-success') }}</div>
        @endif
        @if (session('admin-user-warning'))
            <div class="alert alert-warning">{{ session('admin-user-warning') }}</div>
        @endif
        @if ($errors->any())
            <div class="alert alert-danger">
                <div class="fw-bold">Please correct the following:</div>
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" name="adminUserForm" action="{{ route('admin.users.update', $user) }}"
              x-data="{ is_student: {{ old('is_student', $user->is_student) ? 'true' : 'false' }} }">
            @csrf
            @method('PUT')

            <div class="row row-cards">
                {{-- Profile information --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Profile</h3></div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-sm mb-3">
                                    <label class="form-label required">First Name</label>
                                    <input type="text" name="firstname" class="form-control"
                                           value="{{ old('firstname', $user->firstname) }}" required autocomplete="off">
                                </div>
                                <div class="col-sm mb-3">
                                    <label class="form-label required">Last Name</label>
                                    <input type="text" name="lastname" class="form-control"
                                           value="{{ old('lastname', $user->lastname) }}" required autocomplete="off">
                                </div>
                            </div>

                            <div class="mb-3">
                                <label class="form-label required">Email</label>
                                <input type="email" name="email" class="form-control"
                                       value="{{ old('email', $user->email) }}" required autocomplete="off">
                            </div>

                            <div class="mb-3">
                                <label class="form-label">Street Address</label>
                                <input type="text" name="address1" class="form-control"
                                       value="{{ old('address1', $user->address1) }}" autocomplete="off">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Street Address 2</label>
                                <input type="text" name="address2" class="form-control"
                                       value="{{ old('address2', $user->address2) }}" autocomplete="off">
                            </div>

                            <div class="row">
                                <div class="col-sm-7 mb-3">
                                    <label class="form-label">City</label>
                                    <input type="text" name="city" class="form-control"
                                           value="{{ old('city', $user->city) }}" autocomplete="off">
                                </div>
                                <div class="col-sm-2 mb-3">
                                    <label class="form-label">State</label>
                                    <input type="text" name="state" class="form-control" maxlength="2"
                                           value="{{ old('state', $user->state) }}" autocomplete="off">
                                </div>
                                <div class="col-sm-3 mb-3">
                                    <label class="form-label">Zip</label>
                                    <input type="text" name="zip" class="form-control"
                                           value="{{ old('zip', $user->zip) }}" autocomplete="off">
                                </div>
                            </div>

                            <div class="mb-3 mt-4">
                                <label class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="is_student"
                                           value="1" x-model="is_student">
                                    <span class="form-check-label">Currently a student?</span>
                                </label>
                            </div>

                            <div x-show="is_student" x-transition>
                                <x-student-edit-form :user="$user" />
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Roles --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Roles</h3></div>
                        <div class="card-body">
                            @foreach ($allRoles as $role)
                                <label class="form-check">
                                    <input class="form-check-input" type="checkbox" name="roles[]"
                                           value="{{ $role }}"
                                           @checked(collect(old('roles', $user->getRoleNames()->all()))->contains($role))>
                                    <span class="form-check-label">{{ $role }}</span>
                                </label>
                            @endforeach
                            @if ($user->is(auth()->user()))
                                <div class="form-hint mt-2">You cannot remove <code>super.admin</code> from your own account.</div>
                            @endif
                        </div>
                    </div>
                </div>

                {{-- Committees (read-only; manage membership from the committee) --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Committees</h3></div>
                        <div class="card-body">
                            @forelse ($user->committees as $committee)
                                <div class="d-flex align-items-center justify-content-between py-1 @if(! $loop->last) border-bottom @endif">
                                    <a href="{{ route('admin.committees.edit', $committee) }}">{{ $committee->name }}</a>
                                    <span class="text-secondary small">
                                        added {{ \Illuminate\Support\Carbon::parse($committee->pivot->created_at)->format('M j, Y') }}
                                    </span>
                                </div>
                            @empty
                                <div class="text-secondary">Not on any committees.</div>
                            @endforelse
                        </div>
                    </div>
                </div>

                {{-- Zulip --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Zulip</h3></div>
                        <div class="card-body">
                            <div class="mb-3">
                                <label class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="sync_to_zulip" value="1"
                                           @checked(old('sync_to_zulip', $user->sync_to_zulip))>
                                    <span class="form-check-label">Sync this user to Zulip</span>
                                </label>
                                <div class="form-hint">When on, the nightly sync (and the "Sync to Zulip" button) creates this user in Zulip if needed and pushes their belt rank and group memberships.</div>
                            </div>

                            <p class="text-secondary mb-1">The values that will be pushed:</p>
                            <div class="mb-2">
                                <span class="fw-bold me-1">Belt rank:</span>
                                @if ($zulipBeltRank)
                                    <span class="badge bg-blue-lt">{{ $zulipBeltRank }}</span>
                                @else
                                    <span class="text-secondary">&mdash;</span>
                                @endif
                            </div>
                            <div>
                                <span class="fw-bold me-1">Groups:</span>
                                @forelse ($zulipGroups as $group)
                                    <span class="badge bg-green-lt">{{ $group }}</span>
                                @empty
                                    <span class="text-secondary">None</span>
                                @endforelse
                            </div>
                            <div class="form-hint mt-2">
                                Reflects the currently saved values. Applied to Zulip on the next sync (if the toggle above is on).
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Password --}}
                <div class="col-12">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title">Password</h3></div>
                        <div class="card-body">
                            <p class="text-secondary">Leave blank to keep the current password.</p>
                            <div class="row">
                                <div class="col-sm mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="password" class="form-control" autocomplete="new-password">
                                </div>
                                <div class="col-sm mb-3">
                                    <label class="form-label">Confirm New Password</label>
                                    <input type="password" name="password_confirmation" class="form-control" autocomplete="new-password">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="d-flex">
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </div>
            </div>
        </form>

        {{-- Separate action: email a password reset link --}}
        <div class="card mt-3">
            <div class="card-body d-flex align-items-center justify-content-between">
                <div>
                    <div class="fw-bold">Send password reset email</div>
                    <div class="text-secondary">Emails {{ $user->email }} a link to choose a new password.</div>
                </div>
                <form method="POST" action="{{ route('admin.users.password-reset', $user) }}">
                    @csrf
                    <button type="submit" class="btn btn-outline-primary">Send reset link</button>
                </form>
            </div>
        </div>

    </div>
</div>
@endsection

@push('js')
    <script>
        // Height conversion helpers used by the student-edit-form component (feet/inches to total inches).
        document.addEventListener('DOMContentLoaded', function () {
            if (document.adminUserForm) {
                in2fi(document.adminUserForm);
            }
        });

        function in2fi(frm) {
            let h = frm['height'];
            if (!h) return;
            let feet = frm.height_ft, inches = frm.height_in;
            if (h.value < 1) { feet.value = ''; inches.value = ''; return false; }
            let feet_val = Math.floor(h.value / 12), in_val = h.value % 12;
            if (!isNaN(feet_val) && !isNaN(in_val)) { feet.value = feet_val; inches.value = in_val; }
            else { feet.value = ''; inches.value = ''; }
        }

        function fi2in(frm) {
            let h = frm['height'];
            let feet = parseInt(frm.height_ft.value), inches = parseInt(frm.height_in.value);
            if (!isNaN(feet) && isNaN(inches)) { h.value = parseInt(feet * 12); }
            else if (!isNaN(feet) && !isNaN(inches)) { h.value = parseInt(feet * 12) + parseInt(inches); }
            else { h.value = null; }
        }
    </script>
@endpush
