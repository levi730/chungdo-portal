<div wire:poll.30s class="container-xl">
    @if ($results->isEmpty())
        <div class="card">
            <div class="card-body text-muted">No results yet. This page updates on its own.</div>
        </div>
    @else
        <ul class="nav nav-tabs mb-3">
            <li class="nav-item">
                <button type="button" class="nav-link @unless ($byDivision) active @endunless" wire:click="$set('tab', 'latest')">Latest</button>
            </li>
            <li class="nav-item">
                <button type="button" class="nav-link @if ($byDivision) active @endif" wire:click="$set('tab', 'divisions')">By division</button>
            </li>
        </ul>

        @if ($byDivision)
            @foreach ($results->groupBy('discipline') as $discipline => $divisions)
                @if ($combined)
                    <h2 class="mt-3 mb-3">{{ ucfirst($discipline) }}</h2>
                @endif

                <div class="row row-cards mb-4">
                    @foreach ($divisions as $result)
                        <div class="col-md-6 col-lg-4" wire:key="result-{{ $result->id }}">
                            @include('livewire.event.partials.result-card', ['discipline' => null])
                        </div>
                    @endforeach
                </div>
            @endforeach
        @else
            <div class="row row-cards">
                @foreach ($results as $result)
                    <div class="col-12 col-lg-8" wire:key="latest-{{ $result->id }}">
                        @include('livewire.event.partials.result-card', ['discipline' => $combined ? $result->discipline : null])
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
