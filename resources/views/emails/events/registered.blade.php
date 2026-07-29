@extends('layouts.email')

@section('main')
    <table class="box" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content">
                            <h1>Event Registered</h1>

                            <p>The following event registration has been completed:</p>

                            <h2><a href="{{ config('app.url') }}/event/{{ $event->slug }}/register">{{ $event->name }}</a></h2>


                            <p>{!!  nl2br(e($event->location)) !!}</p>

                            @if(!$event->enddatetime)
                                <p>
                                    {{ $event->startdatetime->format('l, F j, Y') }}
                                    {{ $event->startdatetime->format('g:i a') }}
                                </p>
                            @elseif($event->startdatetime->format('Y-m-d') == $event->enddatetime->format('Y-m-d'))
                                <p>
                                    {{ $event->startdatetime->format('l, F j, Y') }}
                                    {{ $event->startdatetime->format('g:i a') }} - {{ $event->enddatetime->format('g:i a') }}
                                </p>
                            @else
                                <p>{{ $event->startdatetime->format('l, F j, Y @ g:i a') }} - {{ $event->enddatetime->format('l, F j, Y @ g:i a') }}</p>
                            @endif


                            <p>{{ count($registered_users) }} total registration(s) made:</p>
                            <ul>
                                @foreach($registered_users as $user)
                                    <li>{{ $user->full_name }} - ({{ $user->rank->rank }})</li>

                                @endforeach
                            </ul>

                            <p>Total Charge: <b>${{ $amount_paid }}</b></p>


                            @if($qr_image)
                                <h2>Tournament Check-in</h2>

                                <p>Please present this QR Code upon arrival at the tournament location for quicker check-in:</p>

                                <p><img src="<?php echo $message->embed($qr_image, "Entry QR Code"); ?>"></p>

                                <p>Visit the <a href="{{ config('app.url') }}/event/{{ $event->slug }}/register">main registration page</a> to add your event registrations to your mobile wallet.</p>

                            @endif

                            @if($event->waiver_file_path)
                                <p>Please fill out and sign the attached tournament waiver form once for <b>EACH</b> participant.  Bringing this signed form with you to tournament checkin will help speed up the process.</p>
                            @endif

                            <p>
                                Thanks,<br>
                                {{ config('app.name') }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
@endsection
