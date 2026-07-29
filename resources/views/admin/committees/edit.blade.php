@extends('layouts.dashboard')

@section('page-title')
    Edit Committee — {{ $committee->name }}
@endsection

@section('content')
<div class="content">
    <div class="container-xl">
        <div class="d-flex align-items-center mb-3">
            <a href="{{ route('admin.committees.index') }}" class="btn btn-link px-0">&larr; Back to committees</a>
        </div>
        <livewire:admin.committee-form :committee-id="$committee->id" />
    </div>
</div>
@endsection
