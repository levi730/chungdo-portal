<div wire:poll.30s class="container-xl">
    @forelse ($groups as $discipline => $results)
        @if ($combined)
            <h2 class="mt-3 mb-3">{{ ucfirst($discipline) }}</h2>
        @endif

        <div class="row row-cards mb-4">
            @foreach ($results as $result)
                <div class="col-md-6 col-lg-4" wire:key="result-{{ $result->id }}">
                    <div class="card h-100">
                        <div class="card-header">
                            <h3 class="card-title">{{ $result->division_name }}</h3>
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
                </div>
            @endforeach
        </div>
    @empty
        <div class="card">
            <div class="card-body text-muted">No results yet. This page updates on its own.</div>
        </div>
    @endforelse
</div>
