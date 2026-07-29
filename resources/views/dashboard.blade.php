@extends('layouts.app')

@section('page-title')
    Dashboard
@endsection

@section('content')
<div class="container-xl">
    <!--<div class="row mb-5">
        <div class="col-12 bg-primary-lt fs-2 text-center p-3">
            Sparring tournament registration is now open for the Winter 2025 Tournament!
        </div>
    </div>-->
    <div class="row row-cards">
        <div class="col-sm-6">

            <h1 class="display-3">Welcome!</h1>

            <h3>
                You've made it to the main dashboard!
            </h3>

            <p>
                Check back here in the future for more information and events!
            </p>
        </div>

        <div class="col-sm-6">
            @foreach($next_events as $next_event)
            <div class="card mb-5">
                <div class="card-status-top bg-primary"></div>
                <div class="card-body">
                    <h3 class="card-title">{{ $next_event->name }}</h3>
                    <p>{{ $next_event->startdatetime->format('l, F j, Y | g:ia') }}</p>
                    <p>
                        {!! nl2br($next_event->location) !!}
                    </p>

                    @if($next_event->map_url)
                        <iframe src="{{ $next_event->map_url }}" width="600" height="450" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                    @endif

                </div>
                <div class="card-footer">
                    <div class="d-flex">
                        <a href="/event/{{ $next_event->slug }}/register" class="btn btn-primary ms-auto">Details</a>
                    </div>
                </div>
            </div>
            @endforeach

            <div class="card">
                <div class="card-img-top" style="height: 150px; background-size: cover; background-position: center; background-image: url(/img/2025/project_united/ProjectUnitedDesign_card.jpg);"></div>
                <div class="card-body">
                    <h3 class="card-title">Project United</h3>
                    <p>Join us as we unite and lend our support to Miss Tammy in her recovery.</p>
                    <p><b>NEW!</b> Check out the new Project United hoodies!</p>
                </div>
                <div class="card-footer">
                    <div class="d-flex">
                        <a href="/project-united" class="btn btn-primary ms-auto">Details</a>
                    </div>
                </div>
            </div>


            <a href="https://linktr.ee/chungdotkd" target="_blank"><div class="card mt-5">
                <!-- Photo -->
                <div class="img-responsive img-responsive-21x9 card-img-top" style="background-image: url('/img/linktree_chungdotkd.jpg')"></div>
                <div class="card-body">
                    <h3 class="card-title">Linktree</h3>
                    <p class="text-secondary">Check out our Linktree for updates and information across the organization.</p>
                </div>
            </div>
            </a>

        </div>
    </div>
</div>
@endsection
