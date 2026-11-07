<div wire:poll.30s class="container-xl">
    <div class="d-flex justify-content-between align-items-center gap-3 mb-3">
        <p class="text-muted mb-0">
            Mini Pee Wees and Pee Wees who competed and did not place in the top {{ $winningPlaces }} in forms or sparring.
        </p>
        <button type="button" class="btn no-print" onclick="window.print()">Print</button>
    </div>

    <div class="row row-cards mb-4">
        <div class="col-6 col-md-3">
            <div class="card card-body py-3"><div class="h1 mb-0">{{ $ready->count() }}</div><div class="text-muted">Trophies to give</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-body py-3"><div class="h1 mb-0">{{ $ready->where('given', true)->count() }}</div><div class="text-muted">Given</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-body py-3"><div class="h1 mb-0">{{ $waiting->count() }}</div><div class="text-muted">Waiting on a division</div></div>
        </div>
        <div class="col-6 col-md-3">
            <div class="card card-body py-3"><div class="h1 mb-0">{{ $not_checked_in->count() }}</div><div class="text-muted">Not checked in</div></div>
        </div>
    </div>

    @if ($no_birth_date->isNotEmpty())
        <div class="alert alert-warning">
            <strong>No birth date on file, so these competitors can't be sorted by age:</strong>
            {{ $no_birth_date->pluck('name')->implode(', ') }}
        </div>
    @endif

    @forelse ($ready->groupBy(fn ($row) => $row['school'] ?? 'No school') as $school => $rows)
        <div class="card mb-3">
            <div class="card-header">
                <h3 class="card-title">{{ $school }} ({{ $rows->count() }})</h3>
            </div>
            <div class="table-responsive">
                <table class="table table-vcenter card-table">
                    <thead>
                        <tr>
                            <th class="w-1">Given</th>
                            <th>Name</th>
                            <th>Group</th>
                            <th>Divisions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr wire:key="trophy-{{ $row['registration_id'] }}">
                                <td>
                                    <input type="checkbox" class="form-check-input" aria-label="Trophy given to {{ $row['name'] }}"
                                           @checked($row['given'])
                                           wire:click="setGiven({{ $row['registration_id'] }}, {{ $row['given'] ? 'false' : 'true' }})">
                                </td>
                                <td>{{ $row['name'] }}</td>
                                <td>{{ $row['group'] }}, {{ $row['age'] }}</td>
                                <td class="text-muted">{{ implode(', ', $row['divisions']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @empty
        <div class="card mb-3">
            <div class="card-body text-muted">Nobody is ready for a trophy yet. This list fills in as divisions are saved.</div>
        </div>
    @endforelse

    @if ($waiting->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Waiting on a division</h3></div>
            <div class="list-group list-group-flush">
                @foreach ($waiting as $row)
                    <div class="list-group-item" wire:key="waiting-{{ $row['registration_id'] }}">
                        {{ $row['name'] }}
                        <span class="text-muted">&middot; {{ implode(', ', $row['unsaved']) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif

    @if ($not_checked_in->isNotEmpty())
        <div class="card mb-3">
            <div class="card-header"><h3 class="card-title">Not checked in</h3></div>
            <div class="card-body text-muted">{{ $not_checked_in->pluck('name')->implode(', ') }}</div>
        </div>
    @endif
</div>
