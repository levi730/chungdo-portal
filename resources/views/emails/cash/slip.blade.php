@extends('layouts.email')

@section('main')
    <table class="box" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content">
                            <h1>{{ $remittance->reference }}</h1>

                            <p>You registered {{ count($remittance->lines()) }} student(s) for
                                <b>{{ $event->name }}</b>, to be paid by cash or check. You are responsible for
                                turning in <b>${{ number_format($remittance->amount_due, 2) }}</b>.</p>

                            <ul>
                                @foreach($remittance->lines() as $student)
                                    <li>{{ $student['name'] }} &mdash; ${{ number_format($student['amount'], 2) }}</li>
                                @endforeach
                            </ul>

                            <p>Checks are payable to <b>{{ $remittance->payee }}</b>.</p>

                            <p><a href="{{ $slipUrl }}">Print the remittance slip</a> and put it in the envelope
                                with the money, so it can be matched when it is deposited.</p>

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
