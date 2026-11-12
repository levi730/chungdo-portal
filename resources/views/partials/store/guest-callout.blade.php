{{-- The way into Chung Do Gear for someone who is not signed in. A guest who
     types chungdo.org lands on /first-visit or /login, and neither had any
     sign of the store, so the only way in was a link handed to them.

     Shown only while the store is switched on (STORE_MENU) AND something can
     actually be ordered: a card promising a shop with nothing in it is worse
     than no card. Guests can check out, so it says so. --}}
@if(config('store.menu'))
    @php($gearProducts = \App\Models\Product::orderable()->orderBy('sort_order')->orderBy('name')->get())
    @if($gearProducts->isNotEmpty())
        <a href="{{ route('store.index') }}" class="card card-link card-link-pop mt-3 text-reset text-decoration-none" data-store-callout>
            <div class="card-status-top bg-primary"></div>
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <span class="avatar bg-primary-lt me-3">
                        <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M6 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M17 19m-2 0a2 2 0 1 0 4 0a2 2 0 1 0 -4 0"/><path d="M17 17h-11v-14h-2"/><path d="M6 5l14 1l-1 7h-13"/></svg>
                    </span>
                    <div class="flex-fill">
                        <div class="fw-bold">Shop {{ config('store.name') }}</div>
                        <div class="text-muted small">
                            {{ $gearProducts->take(3)->pluck('name')->join(', ', ' and ') }}@if($gearProducts->count() > 3) and more @endif
                            — no account needed.
                        </div>
                    </div>
                    <svg xmlns="http://www.w3.org/2000/svg" class="icon text-muted ms-2" width="24" height="24" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><path d="M9 6l6 6l-6 6"/></svg>
                </div>
            </div>
        </a>
    @endif
@endif
