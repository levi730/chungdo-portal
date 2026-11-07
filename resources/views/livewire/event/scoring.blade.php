<div class="container-xl">
    @if ($versionId)
        <div class="d-flex align-items-center mb-3">
            <button type="button" class="btn btn-link px-0 me-3" wire:click="close">&larr; Divisions</button>
            <h2 class="mb-0">{{ $label }}</h2>
        </div>

        @if ($error)
            <div class="alert alert-danger">{{ $error }}</div>
        @endif

        @php($closed = $result?->isCompleted())
        @php($awaiting = $result?->isAwaitingFinals())

        @if ($closed)
            <div class="alert alert-success">
                Saved. This division is on the results page.
            </div>
        @elseif ($finals)
            <p class="text-muted">Tap the two finalists, then 3rd place. You can type over a number for a tie. 1st and 2nd are saved after finals.</p>
        @elseif ($awaiting)
            <div class="alert alert-info">
                Waiting on finals. After finals, tap 1st, then 2nd, and save results.
            </div>
        @else
            <p class="text-muted">Tap competitors in finishing order. You can type over a number, so a 4 can become a tied 3.</p>
        @endif

        <div class="row row-cards">
            @foreach ($members as $member)
                @php($place = $places[$member->id] ?? null)
                @php($finalist = ! $place && in_array($member->id, $finalists, true))
                <div class="col-6 col-md-4 col-lg-3" wire:key="member-{{ $member->id }}">
                    <div class="card h-100 {{ $place || $finalist ? 'border-primary bg-primary-lt' : '' }}"
                         role="button"
                         @unless($closed) wire:click="mark({{ $member->id }})" @endunless>
                        <div class="card-body d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-bold">{{ $member->user?->full_name }}</div>
                                <div class="text-muted small">{{ $member->user?->school?->shortname }}</div>
                                <div class="text-muted small">{{ $member->user?->rank?->rank }}</div>
                            </div>
                            @if ($finalist)
                                <span class="badge bg-blue text-white ms-2">Finals</span>
                            @elseif ($place)
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
            @elseif ($finals)
                <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">Save until finals</button>
                <button type="button" class="btn btn-link" wire:click="setFinals(false)">Cancel</button>
            @else
                <button type="button" class="btn btn-primary" wire:click="save" wire:loading.attr="disabled">Save results</button>
                <button type="button" class="btn btn-outline-secondary" wire:click="setFinals(true)">
                    {{ $awaiting ? 'Change finalists or 3rd' : '1st and 2nd at finals' }}
                </button>
            @endif
        </div>
    @else
        @forelse ($versions as $discipline => $version)
            <h2 class="mt-3 mb-3">{{ ucfirst($discipline) }}</h2>
            <div class="row row-cards mb-4">
                @foreach ($version->data as $index => $item)
                    @continue(empty($item['members']))
                    @php($itemResult = $scored[$version->id.':'.$index] ?? null)
                    <div class="col-md-6 col-lg-4" wire:key="division-{{ $version->id }}-{{ $index }}">
                        <div class="card" role="button" wire:click="open({{ $version->id }}, {{ $index }})">
                            <div class="card-body d-flex justify-content-between align-items-center">
                                <span>{{ $item['label'] ?? '' }}</span>
                                @if ($itemResult?->isCompleted())
                                    <span class="badge bg-green text-white">Done</span>
                                @elseif ($itemResult?->isAwaitingFinals())
                                    <span class="badge bg-blue text-white">Waiting on finals</span>
                                @elseif ($itemResult)
                                    <span class="badge bg-yellow text-white">Reopened</span>
                                @endif
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @empty
            <div class="card"><div class="card-body text-muted">Nothing to score yet. Publish a version on Arrange Divisions first.</div></div>
        @endforelse
    @endif
</div>
