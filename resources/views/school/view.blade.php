@extends('layouts.dashboard')

@section('page-title')
    {{ $school->name }}
@endsection

@section('content')
    <div class="content">


        <div class="container-xl">

            <div class="row row-cards">
                <div class="col-md-6">
                    <div class="card mb-3">
                        <div class="card-header">
                            <h3 class="card-title">Upcoming Events</h3>
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                @foreach($events as $event)
                                <div class="col-6">
                                    <div class="row g-3 align-items-center">
                                        <div class="col-auto">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="icon-lg icon-tabler icon-tabler-calendar-event" width="128" height="128" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                                <path stroke="none" d="M0 0h24v24H0z" fill="none"></path>
                                                <rect x="4" y="5" width="16" height="16" rx="2"></rect>
                                                <line x1="16" y1="3" x2="16" y2="7"></line>
                                                <line x1="8" y1="3" x2="8" y2="7"></line>
                                                <line x1="4" y1="11" x2="20" y2="11"></line>
                                                <rect x="8" y="15" width="2" height="2"></rect>
                                            </svg>
                                        </div>
                                        <div class="col">
                                            <div class="text-body d-block text-truncate">{{ $event->name }}</div>
                                            <br>
                                            @can('event.viewAllSchoolRegistrants')
                                            <div class="dropdown">

                                                <a id="dLabel" type="button" {{--data-bs-toggle="dropdown" aria-expanded="false"--}} class="btn btn-primary {{--dropdown-toggle--}}" href="{{ route('event.by.school', [$school->id, $event->slug]) }}">
                                                    View Registrants
                                                </a>
                                                {{--<ul class="dropdown-menu" aria-labelledby="dLabel">
                                                    <li><a class="dropdown-item" href="#">Regular link</a></li>
                                                    <li><a class="dropdown-item active" href="#" aria-current="true">Active link</a></li>
                                                    <li><a class="dropdown-item" href="#">Another link</a></li>
                                                </ul>--}}
                                            </div>
                                            @endcan

                                            <small class="text-muted text-truncate mt-n1">{{ $event->startdatetime->format('l, M j Y') }} &middot {{ $event->startdatetime->format('G:i A') }}</small>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>

@endsection

@push('js')

@endpush
