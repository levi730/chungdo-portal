@extends('layouts.email')

@section('main')
    <table class="box" cellpadding="0" cellspacing="0">
        <tr>
            <td>
                <table cellpadding="0" cellspacing="0">
                    <tr>
                        <td class="content">
                            <h1>Cash/check still outstanding</h1>

                            <p>These remittances are for events that have already happened and have not been
                                marked received:</p>

                            <ul>
                                @foreach($remittances as $r)
                                    <li>
                                        <b>{{ $r->reference }}</b> &mdash; {{ $r->event->name }}:
                                        ${{ number_format($r->amount_due, 2) }} from {{ $r->responsible->full_name }}
                                        ({{ count($r->lines()) }} student(s), entered {{ $r->created_at->format('M j') }})
                                    </li>
                                @endforeach
                            </ul>

                            <p><a href="{{ $listUrl }}">Review outstanding remittances</a></p>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>
@endsection
