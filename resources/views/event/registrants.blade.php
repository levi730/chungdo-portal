@extends('layouts.dashboard')


@section('page-title')
    {{ $event->name }} - All Registrations
@endsection

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('content')
<div class="container-xl">
    <table class="table table-bordered">
        @foreach($grouped_data as $school=>$items)

            <tr>
                <th class="bg-primary text-white" colspan="5"><h2>{{ $school }}</h2></th>
                <th class="bg-primary text-white text-end" colspan="{{ $event->hasAddon('guests') ? 6 : 5 }}">Total: {{ count($items) }}</th>
            </tr>
            <tr>
                <th>First Name</th>
                <th>Last Name</th>
                <th>Email</th>
                <th>Rank</th>
                <th>Age</th>
                <th>Sex</th>
                <th>Height</th>
                <th>Weight</th>
                <th>T-Shirt Size</th>
                @if($event->hasAddon('guests'))
                    <th>Guests</th>
                @endif
                <th>Notes</th>
            </tr>

            @foreach($items as $user)

                <tr @if($user->event_notes->count() > 0)
                        class="bg-warning-lt"
                    @endif
                    >
                    <td>{{ $user->firstname }}</td>
                    <td>{{ $user->lastname }}</td>
                    <td><a href="mailto:{{ $user->email }}"> {{ $user->email }}</a></td>
                    <td>{{ $user->rank->rank }}</td>
                    <td>{{ $user->age }}</td>
                    <td>{{ $user->sex }}</td>
                    <td>{{ $user->height_text }}</td>
                    <td>{{ $user->weight }}</td>
                    <td>{{ $user->pivot->tshirtSize() }}</td>
                    @if($event->hasAddon('guests'))
                        <td>{{ $user->pivot->guestCount() }}</td>
                    @endif
                    <td>


                        <a href="" title="Add" data-bs-toggle="modal" data-bs-target="#user{{ $user->id }}Notes">
                            @if($user->event_notes->count() > 0)
                                <span class="text-danger fw-bold">{{ $user->event_notes->count() }}</span>
                            @else
                                {{ $user->event_notes->count() }}
                            @endif



                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-notes" width="44" height="44" viewBox="0 0 24 24" stroke-width="1.5" stroke="#2c3e50" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z" />
                                <path d="M9 7l6 0" />
                                <path d="M9 11l6 0" />
                                <path d="M9 15l4 0" />
                            </svg>
                        </a>

                        @push('modals')
                            @include('partials.event.user-event-notes-modal', ['user' => $user])
                        @endpush

                    </td>
                </tr>
            @endforeach

            @if(!$loop->last)
                <tr>
                    <td colspan="{{ $event->hasAddon('guests') ? 11 : 10 }}">&nbsp;</td>
                </tr>
            @endif
        @endforeach

        <tr>

            <td colspan="{{ $event->hasAddon('guests') ? 11 : 10 }}" class="text-end fs-2 fw-bold">Grand Total: {{ $total_count }}</td>
        </tr>
    </table>
</div>
@endsection
