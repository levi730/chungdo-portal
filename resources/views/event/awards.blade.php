@extends('layouts.dashboard')

@section('subnav')
    @include('partials.event.subnav')
@endsection

@section('page-title')
    {{ $event->name }} Awards
@endsection

@section('content')
    <style>
        .awards-division { break-inside: avoid; }
        .awards-columns { columns: 2 22rem; column-gap: 2rem; }
        .awards-place { display: inline-block; min-width: 3.5rem; font-weight: 600; }
        .awards-section { break-before: auto; }
        .awards-section > h2 { break-after: avoid; }
        @media print {
            @page { margin: 0.5in; }
            .navbar, .no-print, footer, .page-header { display: none !important; }
            .container-xl { max-width: 100% !important; padding: 0 !important; }
            body, .page, .page-wrapper { background: #fff !important; }
            .page-wrapper { margin: 0 !important; }
            .page-body { margin: 0 !important; }
            .awards-columns { columns: 2; }
            .awards-trophies { break-before: page; }
            a { color: inherit; text-decoration: none; }
        }
    </style>

    <div class="content">
        <div class="container-xl">
            <div class="d-flex justify-content-between align-items-start gap-3 mb-3">
                <div>
                    <h1 class="mb-0">{{ $event->name }}</h1>
                    <div class="text-muted">Awards as of {{ now()->format('D g:i A') }}</div>
                </div>
                <button type="button" class="btn no-print" onclick="window.print()">Print</button>
            </div>

            @if ($unsaved !== [])
                <div class="alert alert-warning">
                    <strong>Not saved yet, so not on this sheet ({{ count($unsaved) }}):</strong>
                    {{ implode(', ', $unsaved) }}
                </div>
            @endif

            @if ($has_forms)
                <section class="awards-section mb-4">
                    <h2>Forms: 1st place</h2>
                    @if ($forms === [])
                        <p class="text-muted">No forms divisions saved yet.</p>
                    @else
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr><th>Division</th><th>1st place</th><th>School</th></tr>
                                </thead>
                                <tbody>
                                    @foreach ($forms as $division)
                                        @foreach ($division['winners'] as $winner)
                                            <tr class="awards-division">
                                                <td>{{ $division['division'] }}</td>
                                                <td class="fw-bold">{{ $winner->displayName() }}</td>
                                                <td>{{ $winner->school }}</td>
                                            </tr>
                                        @endforeach
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </section>
            @endif

            @if ($has_sparring)
                <section class="awards-section mb-4">
                    <h2>Sparring</h2>
                    @if ($sparring === [])
                        <p class="text-muted">No sparring divisions saved yet.</p>
                    @else
                        <div class="awards-columns">
                            @foreach ($sparring as $division)
                                <div class="awards-division mb-3">
                                    <div class="fw-bold border-bottom mb-1">{{ $division['division'] }}</div>
                                    @if ($division['awaiting_finals'])
                                        <div class="text-muted small">1st and 2nd: waiting on finals</div>
                                    @endif
                                    @foreach ($division['placements'] as $placement)
                                        <div>
                                            <span class="awards-place">{{ $placement->place ?? 'Finals' }}</span>
                                            {{ $placement->displayName() }}
                                            @if ($placement->school)
                                                <span class="text-muted">({{ $placement->school }})</span>
                                            @endif
                                        </div>
                                    @endforeach
                                </div>
                            @endforeach
                        </div>
                    @endif
                </section>
            @endif

            <section class="awards-section awards-trophies mb-4">
                <h2>Participation trophies ({{ $trophies->count() }})</h2>
                @if ($trophies_waiting > 0)
                    <p class="text-muted">{{ $trophies_waiting }} more {{ Str::plural('child', $trophies_waiting) }} waiting on a division.</p>
                @endif
                @if ($trophies->isEmpty())
                    <p class="text-muted">Nobody is ready for a trophy yet.</p>
                @else
                    <div class="awards-columns">
                        @foreach ($trophies->groupBy(fn ($row) => $row['school'] ?? 'No school') as $school => $rows)
                            <div class="awards-division mb-3">
                                <div class="fw-bold border-bottom mb-1">{{ $school }} ({{ $rows->count() }})</div>
                                @foreach ($rows as $row)
                                    <div>{{ $row['name'] }} <span class="text-muted">({{ $row['group'] }})</span></div>
                                @endforeach
                            </div>
                        @endforeach
                    </div>
                @endif
            </section>
        </div>
    </div>
@endsection
