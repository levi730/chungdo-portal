@extends('layouts.dashboard')


@section('page-title')
    {{ $event->name }} - All Registrations
@endsection

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('content')
<div class="container-xl">
    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    {{-- Column count: the ten fixed ones, plus Guests, plus the Refund column for event.issueRefunds holders. --}}
    @php($cols = 10 + ($event->hasAddon('guests') ? 1 : 0) + (($canRefund ?? false) ? 1 : 0))
    <table class="table table-bordered">
        @foreach($grouped_data as $school=>$items)

            <tr>
                <th class="bg-primary text-white" colspan="5"><h2>{{ $school }}</h2></th>
                <th class="bg-primary text-white text-end" colspan="{{ $cols - 5 }}">Total: {{ count($items) }}</th>
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
                @if($canRefund ?? false)
                    <th>Refund</th>
                @endif
            </tr>

            @foreach($items as $user)

                <tr id="user{{ $user->id }}Row" @class(['bg-warning-lt' => $user->notes->count() > 0])>
                    <td>{{ $user->firstname }}</td>
                    <td>
                        {{ $user->lastname }}
                        @if($user->pivot->cash_remittance_id && isset($awaitingCash[$user->pivot->cash_remittance_id]))
                            <a href="{{ route('cash.slip', $user->pivot->cash_remittance_id) }}" class="badge bg-warning text-white" title="Cash/check not yet received">Unpaid &middot; {{ $awaitingCash[$user->pivot->cash_remittance_id] }}</a>
                        @endif
                    </td>
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
                            {{-- Kept in step with the modal by refreshNoteCount() after an add or delete. --}}
                            <span id="user{{ $user->id }}NoteCount"
                                  @class(['note-count', 'text-danger fw-bold' => $user->notes->count() > 0])>{{ $user->notes->count() }}</span>



                            <svg xmlns="http://www.w3.org/2000/svg" class="icon icon-tabler icon-tabler-notes" width="44" height="44" viewBox="0 0 24 24" stroke-width="1.5" stroke="#2c3e50" fill="none" stroke-linecap="round" stroke-linejoin="round">
                                <path stroke="none" d="M0 0h24v24H0z" fill="none"/>
                                <path d="M5 3m0 2a2 2 0 0 1 2 -2h10a2 2 0 0 1 2 2v14a2 2 0 0 1 -2 2h-10a2 2 0 0 1 -2 -2z" />
                                <path d="M9 7l6 0" />
                                <path d="M9 11l6 0" />
                                <path d="M9 15l4 0" />
                            </svg>
                        </a>

                        @push('modals')
                            @include('partials.event.user-notes-modal', ['user' => $user, 'event' => $event])
                        @endpush

                    </td>
                    @if($canRefund ?? false)
                        <td class="text-nowrap">
                            {{-- Withdraw this person and refund what they paid, no request from them needed. --}}
                            <button type="button" class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#withdraw{{ $user->pivot->id }}">Refund</button>
                            @push('modals')
                                @include('partials.event.withdraw-modal', ['user' => $user, 'event' => $event, 'summary' => $withdrawals[$user->pivot->id]])
                            @endpush
                        </td>
                    @endif
                </tr>
            @endforeach

            @if(!$loop->last)
                <tr>
                    <td colspan="{{ $cols }}">&nbsp;</td>
                </tr>
            @endif
        @endforeach

        <tr>

            <td colspan="{{ $cols }}" class="text-end fs-2 fw-bold">Grand Total: {{ $total_count }}</td>
        </tr>
    </table>
</div>
@endsection
