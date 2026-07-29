@extends('layouts.dashboard')

@section('page-title')
    User Profile
@endsection

@section('content')
<div class="content">


    <div class="container-xl">

        @if (session('email-verification'))
            <div class="alert alert-success">{{ session('email-verification') }}</div>
        @endif

        <div class="row row-cards">
            <div class="col-12">
                <div class="card">

                    <ul class="nav nav-tabs nav-fill" data-bs-toggle="tabs">
                        <li class="nav-item">
                            <a href="#tabs-profile" class="nav-link @if(session('profile_hash') == 'tabs-profile' || empty(session('profile_hash'))) active @endif" data-bs-toggle="tab" data-allow-default="true"><!-- Download SVG icon from http://tabler-icons.io/i/user -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon me-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><circle cx="12" cy="7" r="4"></circle><path d="M6 21v-2a4 4 0 0 1 4 -4h4a4 4 0 0 1 4 4v2"></path></svg>
                                Profile</a>
                        </li>
                        <li class="nav-item">
                            <a href="#tabs-student" class="nav-link @if(session('profile_hash') == 'tabs-student') active @endif" data-bs-toggle="tab" data-allow-default="true"><!-- Download SVG icon from http://tabler-icons.io/i/home -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-karate" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><circle cx="18" cy="4" r="1"></circle><path d="M3 9l4.5 1l3 2.5"></path><path d="M13 21v-8l3 -5.5"></path><path d="M8 4.5l4 2l4 1l4 3.5l-2 3.5"></path></svg>
                                Family Info</a>
                        </li>
                        <li class="nav-item">
                            <a href="#tabs-active-sessions" class="nav-link @if(session('profile_hash') == 'tabs-active-sessions') active @endif" data-bs-toggle="tab" data-allow-default="true"><!-- Download SVG icon from http://tabler-icons.io/i/activity -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-browser" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><rect x="4" y="4" width="16" height="16" rx="1"></rect><line x1="4" y1="8" x2="20" y2="8"></line><line x1="8" y1="4" x2="8" y2="8"></line></svg>
                                Active Sessions</a>
                        </li>
                        <li class="nav-item">
                            <a href="#tabs-change-password" class="nav-link @if(session('profile_hash') == 'tabs-change-password') active @endif"" data-bs-toggle="tab" data-allow-default="true"><!-- Download SVG icon from http://tabler-icons.io/i/activity -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-key" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><circle cx="8" cy="15" r="4"></circle><line x1="10.85" y1="12.15" x2="19" y2="4"></line><line x1="18" y1="5" x2="20" y2="7"></line><line x1="15" y1="8" x2="17" y2="10"></line></svg>
                                Change Password</a>
                        </li>
                        @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::twoFactorAuthentication()))
                        <li class="nav-item">
                            <a href="#tabs-2fa" class="nav-link @if(session('profile_hash') == 'tabs-2fa') active @endif"" data-bs-toggle="tab" data-allow-default="true"><!-- Download SVG icon from http://tabler-icons.io/i/activity -->
                                <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-2fa" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"></path><path d="M7 16h-4l3.47 -4.66a2 2 0 1 0 -3.47 -1.54"></path><path d="M10 16v-8h4"></path><line x1="10" y1="12" x2="13" y2="12"></line><path d="M17 16v-6a2 2 0 0 1 4 0v6"></path><line x1="17" y1="13" x2="21" y2="13"></line></svg>
                                Two-Factor Authentication</a>
                        </li>
                        @endif
                    </ul>
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane @if(session('profile_hash') == 'tabs-profile' || empty(session('profile_hash'))) active show @endif" id="tabs-profile">
                                @include('profile.update-profile-information-form')

                                @if (auth()->user()->committees->isNotEmpty())
                                    <div class="hr-text">My Committees</div>
                                    <div class="row g-2">
                                        @foreach (auth()->user()->committees as $committee)
                                            <div class="col-auto">
                                                <span class="badge bg-blue-lt p-2">
                                                    {{ $committee->name }}
                                                    <span class="text-secondary ms-1">since {{ \Illuminate\Support\Carbon::parse($committee->pivot->created_at)->format('M Y') }}</span>
                                                </span>
                                            </div>
                                        @endforeach
                                    </div>
                                @endif
                            </div>
                            <div class="tab-pane @if(session('profile_hash') == 'tabs-student') active show @endif" id="tabs-student">
                                @include('profile.school-student-form')
                            </div>
                            <div class="tab-pane @if(session('profile_hash') == 'tabs-active-sessions') active show @endif" id="tabs-active-sessions">
                                @include('profile.update-active-sessions-form')
                            </div>
                            <div class="tab-pane @if(session('profile_hash') == 'tabs-change-password') active show @endif" id="tabs-change-password">

                                @include('profile.update-password-form')

                            </div>

                            @if (Laravel\Fortify\Features::enabled(Laravel\Fortify\Features::twoFactorAuthentication()))
                            <div class="tab-pane @if(session('profile_hash') == 'tabs-2fa') active show @endif" id="tabs-2fa">
                                <h2>{{ __('Two Factor Authentication') }}</h2>

                                @if(session('status') == 'two-factor-authentication-enabled')
                                    {{-- Show SVG QR Code, After Enabling 2FA --}}
                                    <div class="alert alert-success" id="enable2fa">
                                        <p class="mb-2">
                                            {{ __('Two factor authentication is now enabled. Scan the following QR code using your phone\'s authenticator application.') }}
                                        </p>
                                        <div>{!! auth()->user()->twoFactorQrCodeSvg() !!}</div>
                                    </div>
                                    <style>
                                        #enable2fa svg {
                                            border: 10px solid #fff;
                                        }
                                    </style>
                                @endif

                                @include('profile.two-factor-authentication-form')
                            </div>
                            @endif


                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>



    <div class="toast align-items-center text-white bg-primary border-0 mt-3 me-2 position-absolute top-0 end-0" style="z-index: 99999;" role="alert" aria-live="assertive" aria-atomic="true" id="toasterElement">
        <div class="d-flex">
            <div class="toast-body" id="toasterElementBody">

            </div>
            <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    </div>


</div>

@endsection

@push('js')
    <script>
        function submit_user_form(form_element)
        {
            var fd = new FormData();

            for(var i = 0; i < form_element.length; i++)
            {
                if(form_element[i].type == 'file') {
                    fd.append(form_element[i].name, form_element[i].files[0]);
                } else {
                    fd.append(form_element[i].name, form_element[i].value);
                }

            }


            axios.post(form_element.action, fd, {
                headers: {
                    'Content-Type': 'multipart/form-data'
                }
            }).then((response) => {
                let mdiv = form_element.closest('.avatar-anchor');
                let avatarSpan = mdiv.querySelector('span.avatar');
                let asrc = response.data.newsrc;
                if(asrc) {
                    asrc = "url('/storage/avatars/" + response.data.newsrc + "')"
                } else {
                    asrc = "url('/img/default_avatar.png')"
                }
                avatarSpan.style.backgroundImage =  asrc;
                toaster('success', response.data.success);
            }, (error) => {
                toaster('success', response.data.errors.avatar);
            });


        }

        function toaster(type, msg) {
            toastEl = document.getElementById("toasterElement")

            toastEl.classList.remove('bg-danger');
            toastEl.classList.remove('bg-primary');
            toastEl.classList.remove('bg-success');

            toastEl.classList.add('bg-'+type);

            toastElBody = document.getElementById("toasterElementBody");
            toastElBody.innerHTML = msg;


            toast = new bootstrap.Toast(toastEl);
            toast.show();

        }
    </script>
@endpush
