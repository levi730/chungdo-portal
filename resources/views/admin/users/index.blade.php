@extends('layouts.dashboard')

@section('page-title')
    User Administration
@endsection

@section('content')
<div class="content">
    <div class="container-xl">
        @if (session('admin-user-success'))
            <div class="alert alert-success">{{ session('admin-user-success') }}</div>
        @endif

        <div class="row row-cards">
            {{-- Zulip sync --}}
            <div class="col-12">
                <div class="card">
                    <div class="card-body d-flex align-items-center justify-content-between flex-wrap gap-2">
                        <div>
                            <div class="fw-bold">Zulip sync</div>
                            <div class="text-secondary">
                                Pushes belt rank and group memberships for users flagged
                                <span class="badge bg-blue-lt">Sync to Zulip</span>.
                                Runs nightly; use the button to run it now.
                                @unless ($zulipConfigured)
                                    <span class="text-danger d-block">Not configured — set ZULIP_SITE, ZULIP_BOT_EMAIL, ZULIP_BOT_API_KEY.</span>
                                @endunless
                            </div>
                            @if ($lastSync)
                                <div class="text-secondary small mt-1">
                                    Last run {{ $lastSync['finished_at'] ?? '?' }} —
                                    @if (($lastSync['ok'] ?? false))
                                        {{ $lastSync['eligible'] ?? 0 }} eligible,
                                        {{ count($lastSync['unmatched'] ?? []) }} not in Zulip yet,
                                        {{ $lastSync['belt_rank_updated'] ?? 0 }} belt ranks,
                                        {{ count($lastSync['groups'] ?? []) }} groups changed.
                                        @if (! empty($lastSync['errors']))
                                            <span class="text-danger">{{ count($lastSync['errors']) }} error(s).</span>
                                        @endif
                                    @else
                                        <span class="text-danger">failed: {{ $lastSync['errors'][0] ?? 'unknown error' }}</span>
                                    @endif
                                </div>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('admin.zulip.sync') }}">
                            @csrf
                            <button type="submit" class="btn btn-outline-primary" @disabled(! $zulipConfigured)>
                                Sync to Zulip now
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h3 class="card-title">User Administration</h3>
                    </div>
                    <div class="card-body">
                        <livewire:admin.users-table />
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
