@extends('layouts.auth', ['container_class' => 'container-narrow'])

@section('content')


    <div class="card-body">
        <h2 class="mb-3 display-3 text-center">Welcome!</h2>
        <p class="text-center small mb-4"><a href="/login">Login</a> | <a href="/register">Register</a> </p>
        <p class="intro-text">Welcome to the new information portal for the Chung Do Association!</p>

        <p class="intro-text mb-3">
            Follow the checklist below to get registered for this site, and any upcoming events!
        </p>

        <div class="card mt-4">
            <div class="card-status-top bg-primary"></div>
            <div class="card-body">
                <h3 class="card-title">1. Create an account</h3>
                <p>
                    To begin, you will need to create and confirm an account on the portal.  You only need one account
                    per family, but you are free to create as many accounts as you like!
                </p>
                <div class="card-text">
                    <a href="/register" class="btn btn-primary">Register</a>
                </div>
            </div>
        </div>


        <div class="card mt-4">
            <div class="card-status-top bg-primary"></div>
            <div class="card-body">
                <h3 class="card-title">2. Confirm your email</h3>
                <p>
                    After you register with your email address and create a password, you will receive an activation email.
                    That email will provide you with an activation link.  Clicking the activation link from the email will bring
                    you right back to the portal, and your account will be ready to go!
                </p>
            </div>
        </div>


        <div class="card mt-4">
            <div class="card-status-top bg-primary"></div>
            <div class="card-body">
                <h3 class="card-title">3. Fill out your profile</h3>
                <p>
                    From there, it's time to fill out your profile.  If you are a currently active student, you will be able
                    to enter your profile information (including which school you primarialy attend, what your belt rank is, etc).
                    If you are a parent and don't train with us, that's OK (but you really should consider it!).  Your profile
                    page will have options to fill out the same info for your family members.
                </p>
                <div class="card-text">
                    <a href="/profile" class="btn btn-primary">Profile</a>
                </div>
            </div>
        </div>


        <div class="card mt-4">
            <div class="card-status-top bg-primary"></div>
            <div class="card-body">
                <h3 class="card-title">3. Register for events!</h3>
                <p>
                    When your profile is completed, you're ready to register for the tournament and other events!  You
                    can find a link to the tournament registration right on the dashboard/home page.  Check back in the future
                    for further events!
                </p>
                <div class="card-text">
                    <a href="/event/latest" class="btn btn-primary">Info for our latest event</a>
                </div>
            </div>
        </div>


    </div>


@endsection


@push('css')
    <style>
        p.intro-text {
            font-size: 1.2rem;
        }

        div.card-body p {
            font-size: 1.1rem;
        }

        .card-title {
            font-size: 1.3rem;
        }
    </style>
@endpush
