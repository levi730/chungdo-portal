{{-- The state that matters at a glance: is a run taking orders, and if not,
     is another one coming. --}}
@php($open = $product->openRun())
@php($next = $open ? null : $product->nextRun())

@if($open)
    <span class="badge bg-green-lt">{{ $open->name }}</span>
    @if($open->closes_at)
        <span class="text-muted d-block small">until {{ $open->closes_at->format('M j, Y') }}</span>
    @endif
@elseif($next)
    <span class="badge bg-azure-lt">{{ $next->name }}</span>
    <span class="text-muted d-block small">opens {{ $next->opens_at->format('M j, Y') }}</span>
@elseif($product->runs_count ?? 0)
    <span class="text-muted">Closed</span>
@else
    <span class="text-muted">No runs</span>
@endif
