@extends('layouts.dashboard')

@section('page-title')
    Manage Events
@endsection

@section('content')
<div class="container-xl">
    <div class="content">
        <div class="d-flex justify-content-between align-items-center mb-3">
            <h2 class="mb-0">Events</h2>
            <a href="{{ route('events.create') }}" class="btn btn-primary">
                <svg xmlns="http://www.w3.org/2000/svg" class="icon" width="20" height="20" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" fill="none" stroke-linecap="round" stroke-linejoin="round"><path stroke="none" d="M0 0h24v24H0z" fill="none"/><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                Add Event
            </a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        <div class="card">
            <div class="table-responsive">
                <table class="table table-vcenter">
                    <thead>
                        <tr><th>Name</th><th>Type</th><th>Date</th><th class="text-center">Registrations</th><th class="text-center">Add-ons</th><th></th></tr>
                    </thead>
                    <tbody>
                        @forelse($events as $event)
                            <tr @class(['text-muted' => $event->trashed()])>
                                <td>
                                    {{ $event->name }}
                                    @if($event->trashed())<span class="badge bg-secondary text-white ms-1">Archived</span>@endif
                                </td>
                                <td>{{ $event->typeLabel() ?? '—' }}</td>
                                <td>{{ optional($event->startdatetime)->format('M j, Y') ?? '—' }}</td>
                                <td class="text-center">{{ $event->registrations_count }}</td>
                                <td class="text-center">{{ $event->addons->where('enabled', true)->count() }}</td>
                                <td class="text-end">
                                    @if($event->trashed())
                                        <form method="POST" action="{{ route('events.restore', $event->id) }}" class="d-inline">
                                            @csrf
                                            <button class="btn btn-sm btn-outline-success">Restore</button>
                                        </form>
                                    @else
                                        <a href="{{ route('events.edit', $event) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        @if($event->slug)
                                            <a href="{{ route('event.register', $event->slug) }}" class="btn btn-sm btn-outline-secondary">View</a>
                                        @endif
                                        <form method="POST" action="{{ route('events.destroy', $event) }}" class="d-inline" onsubmit="return confirm('Archive this event? Its data is kept and it can be restored.')">
                                            @csrf @method('DELETE')
                                            <button class="btn btn-sm btn-outline-danger">Archive</button>
                                        </form>
                                    @endif
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="6" class="text-center text-muted">No events yet.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection
