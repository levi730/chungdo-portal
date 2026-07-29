@extends('layouts.dashboard')

@section('page-title')
    {{ $event->name }} - Refund Requests
@endsection

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('content')
<div class="container-xl">
    <div class="content">

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        <p class="text-muted">
            Add-on refund requests from registrants. Approving issues the Stripe refund
            and applies the change. You can lower the amount before approving (e.g. to
            withhold the processing fee).
        </p>

        @forelse($requests as $req)
            @php($reg = $req->registration)
            <div class="card mb-3">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <h3 class="mb-0">{{ $reg->user->full_name ?? 'Unknown' }}</h3>
                            <div class="text-muted small">
                                Requested {{ $req->created_at->format('M j, Y g:i a') }}
                                @if($req->requestedBy) by {{ $req->requestedBy->full_name }} @endif
                            </div>
                        </div>
                        <div>
                            @if($req->status === 'pending')
                                <span class="badge bg-yellow text-dark">Pending</span>
                            @elseif($req->status === 'approved')
                                <span class="badge bg-green text-white">Approved</span>
                            @elseif($req->status === 'superseded')
                                <span class="badge bg-secondary text-white">Superseded</span>
                            @else
                                <span class="badge bg-secondary text-white">Denied</span>
                            @endif
                        </div>
                    </div>

                    <table class="table table-sm mt-3 mb-2" style="max-width: 40rem;">
                        <thead><tr><th>Add-on</th><th>Change</th><th class="text-end">Refund</th></tr></thead>
                        <tbody>
                            @foreach($req->summary_lines as $line)
                                <tr>
                                    <td>{{ $line['label'] }}</td>
                                    <td>
                                        <span class="text-muted">{{ $line['fromText'] }}</span>
                                        <span class="mx-1">&rarr;</span>
                                        <span class="fw-bold">{{ $line['toText'] }}</span>
                                    </td>
                                    <td class="text-end text-red">&minus;${{ number_format(max(0, $line['from'] - $line['to']), 2) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    @if($req->status === 'pending')
                        <div class="row g-2 align-items-end mt-2">
                            <form method="POST" action="{{ route('event.refund-approve', [$event->slug, $req->id]) }}" class="col-auto d-flex align-items-end gap-2">
                                @csrf
                                <div>
                                    <label class="form-label mb-0">Refund amount</label>
                                    <div class="input-group input-group-flat" style="width: 10rem;">
                                        <span class="input-group-text">$</span>
                                        <input type="number" step="0.01" min="0" max="{{ $req->refund_amount }}"
                                               name="amount" value="{{ $req->refund_amount }}" class="form-control">
                                    </div>
                                    <small class="text-muted">Computed: ${{ number_format($req->refund_amount, 2) }}</small>
                                </div>
                                <input type="text" name="note" class="form-control" placeholder="Note (optional)" style="width: 16rem;">
                                <button class="btn btn-success" type="submit">Approve &amp; Refund</button>
                            </form>
                            <form method="POST" action="{{ route('event.refund-deny', [$event->slug, $req->id]) }}" class="col-auto">
                                @csrf
                                <button class="btn btn-outline-danger" type="submit">Deny</button>
                            </form>
                        </div>
                    @elseif($req->status === 'superseded')
                        <div class="text-muted small">Superseded by a newer change to this registration.</div>
                    @else
                        <div class="text-muted small">
                            {{ ucfirst($req->status) }}
                            @if($req->decided_at) on {{ $req->decided_at->format('M j, Y') }} @endif
                            @if($req->status === 'approved')
                                — refunded ${{ number_format($req->refund_amount, 2) }}
                                @if($req->stripe_refund_id) ({{ $req->stripe_refund_id }}) @endif
                            @endif
                            @if($req->admin_note) · “{{ $req->admin_note }}” @endif
                        </div>
                    @endif
                </div>
            </div>
        @empty
            <div class="alert alert-info">No refund requests.</div>
        @endforelse

    </div>
</div>
@endsection
