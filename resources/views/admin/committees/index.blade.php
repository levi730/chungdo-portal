@extends('layouts.dashboard')

@section('page-title')
    Committees
@endsection

@section('content')
<div class="content">
    <div class="container-xl">
        @if (session('admin-committee-success'))
            <div class="alert alert-success">{{ session('admin-committee-success') }}</div>
        @endif

        <div class="row row-cards">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex align-items-center">
                        <h3 class="card-title">Committees</h3>
                        <a href="{{ route('admin.committees.create') }}" class="btn btn-primary ms-auto">
                            New Committee
                        </a>
                    </div>
                    <div class="card-body">
                        <livewire:admin.committees-table />
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
