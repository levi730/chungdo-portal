@extends('layouts.dashboard')

@section('page-title')
    {{ $remittance->reference }} &mdash; {{ $remittance->event->name }}
@endsection

@section('content')
<style>
    @media print {
        .navbar, .page-header, .no-print, footer { display: none !important; }
        .slip { border: 2px solid #000 !important; }
    }
</style>
<div class="container-xl">
    @if (session('success'))
        <div class="alert alert-success no-print">{{ session('success') }}</div>
    @endif
    @if ($errors->any())
        <div class="alert alert-danger no-print">{{ $errors->first() }}</div>
    @endif

    <div class="d-flex mb-3 no-print">
        <a href="{{ route('cash.remittances') }}" class="btn btn-link px-0">&larr; Cash/check remittances</a>
        <button type="button" class="btn btn-primary ms-auto" onclick="window.print()">Print slip</button>
    </div>

    <div class="card slip mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <div class="text-secondary text-uppercase small">Cash/check remittance</div>
                    <h1 class="mb-0">{{ $remittance->reference }}</h1>
                    <div>{{ $remittance->event->name }}@if($remittance->event->startdatetime) &middot; {{ $remittance->event->startdatetime->format('M j, Y') }}@endif</div>
                </div>
                <div class="text-end">
                    @switch($remittance->status)
                        @case('outstanding') <span class="badge bg-warning text-white fs-4">Outstanding</span> @break
                        @case('received') <span class="badge bg-success text-white fs-4">Received</span> @break
                        @case('void') <span class="badge bg-secondary text-white fs-4">Void</span> @break
                    @endswitch
                </div>
            </div>

            <div class="row mb-3">
                <div class="col-sm-6">
                    <div class="text-secondary small">Responsible instructor</div>
                    <div class="fw-bold">{{ $remittance->responsible->full_name }}</div>
                    @if ($remittance->created_by_user_id !== $remittance->responsible_user_id)
                        <div class="small text-secondary">Entered by {{ $remittance->createdBy->full_name }}</div>
                    @endif
                    <div class="small text-secondary">{{ $remittance->created_at->format('M j, Y g:i a') }}</div>
                </div>
                <div class="col-sm-6">
                    <div class="text-secondary small">Checks payable to</div>
                    <div class="fw-bold">{{ $remittance->payee }}</div>
                </div>
            </div>

            <table class="table table-sm">
                <thead><tr><th>Student</th><th>Includes</th><th class="text-end">Amount</th></tr></thead>
                <tbody>
                    @foreach ($remittance->lines() as $line)
                        <tr>
                            <td>{{ $line['name'] }}</td>
                            <td class="small text-secondary">
                                {{ collect($line['lines'])->map(fn ($l) => $l['amount'] > 0 || $l['type'] === 'tshirt' ? $l['label'].($l['summary'] && $l['type'] !== 'registration_fee' ? ': '.$l['summary'] : '') : null)->filter()->implode(' · ') }}
                            </td>
                            <td class="text-end">${{ number_format($line['amount'], 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot>
                    <tr><th colspan="2" class="text-end">Total due</th><th class="text-end fs-3">${{ number_format($remittance->amount_due, 2) }}</th></tr>
                </tfoot>
            </table>

            @if ($remittance->note)
                <p class="mb-0"><span class="text-secondary">Note:</span> {{ $remittance->note }}</p>
            @endif

            @if ($remittance->isReceived())
                <hr>
                <p class="mb-1">
                    <b>${{ number_format($remittance->amount_received, 2) }}</b> received
                    ({{ \App\Models\CashRemittance::METHODS[$remittance->received_method] ?? $remittance->received_method }}@if($remittance->check_numbers), checks {{ $remittance->check_numbers }}@endif)
                    by {{ $remittance->receivedBy?->full_name }} on {{ $remittance->received_at->format('M j, Y') }}.
                </p>
                @if (abs($remittance->balance()) >= 0.005)
                    <p class="mb-1 text-danger">
                        {{ $remittance->balance() > 0 ? '$'.number_format($remittance->balance(), 2).' short' : '$'.number_format(-$remittance->balance(), 2).' over' }}.
                    </p>
                @endif
                @if ($remittance->deposit_note)
                    <p class="mb-0 text-secondary">{{ $remittance->deposit_note }}</p>
                @endif
            @elseif ($remittance->isVoid())
                <hr>
                <p class="mb-0">Voided by {{ $remittance->voidedBy?->full_name }} on {{ $remittance->voided_at->format('M j, Y') }}: {{ $remittance->void_reason }}</p>
            @endif
        </div>
    </div>

    @if ($remittance->isOutstanding())
        <div class="row no-print">
            @can('receive', $remittance)
                <div class="col-md-7 mb-3">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title mb-0">Mark received</h3></div>
                        <form method="POST" action="{{ route('cash.receive', $remittance) }}" class="card-body">
                            @csrf
                            <div class="row">
                                <div class="col-sm-5 mb-3">
                                    <label class="form-label">Amount received</label>
                                    <div class="input-group">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" name="amount" class="form-control" required
                                               value="{{ old('amount', number_format($remittance->amount_due, 2, '.', '')) }}">
                                    </div>
                                </div>
                                <div class="col-sm-7 mb-3">
                                    <label class="form-label">Method</label>
                                    <select name="method" class="form-select" required>
                                        @foreach (\App\Models\CashRemittance::METHODS as $value => $label)
                                            <option value="{{ $value }}" @selected(old('method') === $value)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Check numbers <span class="text-secondary">(optional)</span></label>
                                <input type="text" name="check_numbers" class="form-control" value="{{ old('check_numbers') }}" placeholder="1041, 2213">
                            </div>
                            <div class="mb-3">
                                <label class="form-label">Deposit note <span class="text-secondary">(optional)</span></label>
                                <input type="text" name="deposit_note" class="form-control" value="{{ old('deposit_note') }}" placeholder="Deposited 10/14, slip #…">
                            </div>
                            <button class="btn btn-success">Mark received</button>
                        </form>
                    </div>
                </div>
            @endcan
            @can('void', $remittance)
                <div class="col-md-5 mb-3">
                    <div class="card">
                        <div class="card-header"><h3 class="card-title mb-0">Void</h3></div>
                        <form method="POST" action="{{ route('cash.void', $remittance) }}" class="card-body"
                              onsubmit="return confirm('Void {{ $remittance->reference }}? Its {{ count($remittance->lines()) }} registration(s) will be removed.');">
                            @csrf
                            <p class="text-secondary small">Entered by mistake? Voiding removes these registrations. Only possible before the money is received.</p>
                            <div class="mb-3">
                                <input type="text" name="reason" class="form-control" required placeholder="Reason">
                            </div>
                            <button class="btn btn-outline-danger">Void and remove registrations</button>
                        </form>
                    </div>
                </div>
            @endcan
        </div>
    @endif
</div>
@endsection
