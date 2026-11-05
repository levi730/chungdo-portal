<div class="container-xl">
    @if ($division)
        <div class="d-flex align-items-center mb-3">
            <button type="button" class="btn btn-link px-0 me-3" wire:click="close">&larr; Divisions</button>
            <h2 class="mb-0">{{ $division->name }}</h2>
        </div>

        @if ($error)
            <div class="alert alert-danger">{{ $error }}</div>
        @endif

        @php($closed = $result?->isCompleted())

        @if ($closed)
            <div class="alert alert-success">
                Saved. This division is on the results page.
            </div>
        @else
            <p class="text-muted">Tap competitors in finishing order. You can type over a number, so a 4 can become a tied 3.</p>
        @endif

        <div class="row row-cards">
            @foreach ($members as $member)
                @php($place = $places[$member->id] ?? null)
                <div class="col-6 col-md-4 col-lg-3" wire:key="member-{{ $member->id }}">
                    <div class="card h-100 {{ $place ? 'border-primary bg-primary-lt' : '' }}"
                         role="button"
                         @unless($closed) wire:click="mark({{ $member->id }})" @endunless>
                        <div class="card-body d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-bold">{{ $member->user?->full_name }}</div>
                                <div class="text-muted small">{{ $member->user?->school?->shortname }}</div>
                                <div class="text-muted small">{{ $member->user?->rank?->rank }}</div>
                            </div>
                            @if ($place)
                                <input type="number" min="1" max="{{ $members->count() }}"
                                       class="form-control form-control-sm text-center ms-2" style="width: 4rem"
                                       aria-label="Place for {{ $member->user?->full_name }}"
                                       wire:model.blur="places.{{ $member->id }}"
                                       x-on:click.stop
                                       @disabled($closed)>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="mt-3">
            @if ($closed)
                @can('event.manage')
                    <button type="button" class="btn btn-outline-primary" wire:click="reopen"
                            wire:confirm="Reopen this division? It leaves the results page until it is saved again.">
                        Reopen to correct
                    </button>
                @else
                    <span class="text-muted">An event admin can reopen this division if it needs correcting.</span>
                @endcan
            @else
                <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">Save results</button>
            @endif
        </div>
    @else
        @foreach ($drifted as $discipline)
            <div class="alert alert-warning">
                The {{ $discipline }} board has been saved since it was published, so these divisions may not match the printed cards.
            </div>
        @endforeach

        @can('event.manage')
            <div class="card mb-3">
                <div class="card-body d-flex justify-content-between align-items-center">
                    <div>
                        <strong>Results page:</strong>
                        {{ $event->results_public ? 'public, anyone with the link can see full names' : 'signed-in members only' }}
                        <div class="small"><a href="{{ route('event.results', $event->slug) }}">{{ route('event.results', $event->slug) }}</a></div>
                    </div>
                    <button type="button" class="btn btn-outline-primary" wire:click="togglePublic">
                        {{ $event->results_public ? 'Make private' : 'Make public' }}
                    </button>
                </div>
            </div>
        @endcan

        @forelse ($groups as $discipline => $divisions)
            <h2 class="mt-3 mb-3">{{ ucfirst($discipline) }}</h2>
            <div class="row row-cards mb-4">
                @foreach ($divisions as $item)
                    @php($itemResult = $results[$item->id] ?? null)
                    <div class="col-md-6 col-lg-4" wire:key="division-{{ $item->id }}">
                        <div class="card" role="button" wire:click="open({{ $item->id }})">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <span>{{ $item->name }}</span>
                                @if ($itemResult?->isCompleted())
                                    <span class="badge bg-green text-white">Done</span>
                                @elseif ($itemResult)
                                    <span class="badge bg-yellow text-white">Reopened</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="card"><div class="card-body text-muted">This event has no saved divisions.</div></div>
        @endforelse
    @endif
</div>
