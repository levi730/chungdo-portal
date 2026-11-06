<div class="card h-100">
    <div class="card-header">
        <div>
            <h3 class="card-title">{{ $result->division_name }}</h3>
            <div class="text-muted small">
                @if ($discipline){{ ucfirst($discipline) }} &middot; @endif{{ $result->completed_at->format('D g:i A') }}
            </div>
        </div>
    </div>
    <div class="list-group list-group-flush">
        @foreach ($result->placements as $placement)
            <div class="list-group-item d-flex align-items-center">
                <span class="badge bg-blue text-white me-3">{{ $placement->place }}</span>
                <div>
                    <div>{{ $placement->displayName() }}</div>
                    @if ($placement->school)
                        <div class="text-muted small">{{ $placement->school }}</div>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
