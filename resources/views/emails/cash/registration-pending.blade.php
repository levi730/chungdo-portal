@extends('layouts.email')

@section('main')
    <table class="box" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content">
                            <h1>Event Registered</h1>

                            <p>{{ $remittance->responsible->full_name }} has registered the following for
                                <a href="{{ config('app.url') }}/event/{{ $event->slug }}/register">{{ $event->name }}</a>:</p>

                            <ul>
                                @foreach($students as $student)
                                    <li>{{ $student['name'] }} &mdash; ${{ number_format($student['amount'], 2) }}</li>
                                @endforeach
                            </ul>

                            <p>{!! nl2br(e($event->location)) !!}</p>
                            @if($event->startdatetime)
                                <p>{{ $event->startdatetime->format('l, F j, Y @ g:i a') }}</p>
                            @endif

                            <h2>Payment pending: ${{ number_format($amount, 2) }}</h2>

                            <p>This registration is paid by <b>cash or check</b> through your instructor,
                                {{ $remittance->responsible->full_name }}. If you have not already paid, please give
                                them ${{ number_format($amount, 2) }}. Checks are payable to
                                <b>{{ $remittance->payee }}</b>.</p>

                            <p>Reference: {{ $remittance->reference }}</p>

                            @if($qr_image)
                                <h2>Check-in</h2>
                                <p>Please present this QR Code upon arrival for quicker check-in:</p>
                                <p><img src="<?php echo $message->embed($qr_image, "Entry QR Code"); ?>"></p>
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
