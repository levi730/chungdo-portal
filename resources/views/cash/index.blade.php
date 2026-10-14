@extends('layouts.dashboard')

@section('page-title')
    Cash/check remittances
@endsection

@section('content')
<div class="container-xl">
    @if (session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="d-flex align-items-center mb-3">
        <div class="btn-group">
            @foreach (['outstanding' => 'Outstanding', 'received' => 'Received', 'void' => 'Void', 'all' => 'All'] as $value => $label)
                <a href="{{ route('cash.remittances', array_filter(['status' => $value, 'event' => request('event')])) }}"
                   class="btn {{ $status === $value ? 'btn-primary' : 'btn-outline-primary' }}">{{ $label }}</a>
            @endforeach
        </div>
        @unless ($all)
            <span class="ms-3 text-secondary">Remittances you are responsible for or entered.</span>
        @endunless
    </div>

    @if ($remittances->isEmpty())
        <div class="text-secondary">Nothing here.</div>
    @else
        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th>Reference</th>
                            <th>Event</th>
                            <th>Responsible</th>
                            <th class="text-end">Students</th>
                            <th class="text-end">Due</th>
                            <th>Entered</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($remittances as $r)
                            <tr>
                                <td><a href="{{ route('cash.slip', $r) }}">{{ $r->reference }}</a></td>
                                <td>{{ $r->event->name }}</td>
                                <td>{{ $r->responsible->full_name }}</td>
                                <td class="text-end">{{ count($r->lines()) }}</td>
                                <td class="text-end">${{ number_format($r->amount_due, 2) }}</td>
                                <td>{{ $r->created_at->format('M j, Y') }}</td>
                                <td>
                                    @if ($r->isOutstanding())
                                        <span class="badge bg-warning text-white">Outstanding</span>
                                    @elseif ($r->isReceived())
                                        <span class="badge bg-success text-white">Received</span>
                                        @if (abs($r->balance()) >= 0.005)
                                            <span class="badge bg-danger text-white">{{ $r->balance() > 0 ? 'short' : 'over' }} ${{ number_format(abs($r->balance()), 2) }}</span>
                                        @endif
                                    @else
                                        <span class="badge bg-secondary text-white">Void</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                    @if ($status === 'outstanding')
                        <tfoot>
                            <tr>
                                <th colspan="4" class="text-end">Outstanding total</th>
                                <th class="text-end">${{ number_format($remittances->sum(fn ($r) => (float) $r->amount_due), 2) }}</th>
                                <th colspan="2"></th>
                            </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
        <div class="mt-3">{{ $remittances->links() }}</div>
    @endif
</div>
@endsection
