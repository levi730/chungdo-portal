@extends('layouts.email')

@section('main')
    <table class="box" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content">
                            <h1><a href="{{ config('app.url') }}/project-united">Project United</a></h1>

                            <p>Donation Receipt:</p>

                            <p>Donation Amount: <b>${{ $trans->amount }}</b></p>
                            <p>Donation Date: <b>{{ $trans->created_at->format('m/d/Y g:i a') }}</b></p>

                            <p>Thank you for your donation, and keep training hard!</p>
                            <p>
                                {{ config('app.name') }}
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
@endsection
