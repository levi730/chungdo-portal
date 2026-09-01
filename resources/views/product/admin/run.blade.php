@extends('layouts.dashboard')

@section('page-title')
    {{ $creating ? 'New run: '.$product->name : $run->name.' — '.$product->name }}
@endsection

@section('content')
<div class="container-xl">
    <div class="content">
        <div class="mb-3">
            <a href="{{ route('products.edit', $product) }}" class="btn btn-link px-0">&larr; Back to {{ $product->name }}</a>
        </div>

        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        @if(session('error'))
            <div class="alert alert-danger">{{ session('error') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <form method="POST"
              action="{{ $creating ? route('products.runs.store', $product) : route('products.runs.update', [$product, $run]) }}">
            @csrf
            @unless($creating) @method('PUT') @endunless

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">This run</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label required">Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $run->name) }}"
                                   placeholder="Fall 2026" required>
                            <small class="form-hint">Only staff see this. It labels the pick list.</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Expected arrival</label>
                            <input type="date" name="expected_arrival_at" class="form-control"
                                   value="{{ old('expected_arrival_at', optional($run->expected_arrival_at)->format('Y-m-d')) }}">
                            <small class="form-hint">The print shop's estimate, shown to the buyer.</small>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Order</label>
                            <input type="number" name="sort_order" class="form-control" min="0"
                                   value="{{ old('sort_order', $run->sort_order ?? 0) }}">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Orders open</label>
                            <input type="datetime-local" name="opens_at" class="form-control"
                                   value="{{ old('opens_at', optional($run->opens_at)->format('Y-m-d\TH:i')) }}">
                            <small class="form-hint">Leave blank to open as soon as the product is Active.</small>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Orders close</label>
                            <input type="datetime-local" name="closes_at" class="form-control"
                                   value="{{ old('closes_at', optional($run->closes_at)->format('Y-m-d\TH:i')) }}">
                            <small class="form-hint">
                                Leave blank for no deadline. There is no stock count — this run is
                                sized from the orders taken by this date.
                            </small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Pickup note</label>
                        <input type="text" name="pickup_note" class="form-control"
                               value="{{ old('pickup_note', $run->pickup_note) }}"
                               placeholder="Pick up at your school after Oct 15">
                        <small class="form-hint">Shown to the buyer at checkout. Pickup only — there is no shipping yet.</small>
                    </div>

                    <small class="form-hint">
                        Only one run of a design can be open at a time, so these dates can't overlap another run's.
                    </small>
                </div>
            </div>

            @if($previousRuns->isNotEmpty())
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title mb-0">Start from an earlier run</h3></div>
                    <div class="card-body">
                        <label class="form-label">Copy the price list from</label>
                        <select name="copy_from_run_id" class="form-select">
                            <option value="">Don't copy — start empty</option>
                            @foreach($previousRuns as $previous)
                                <option value="{{ $previous->id }}">
                                    {{ $previous->name }} ({{ $previous->variants_count }} {{ Str::plural('variant', $previous->variants_count) }})
                                </option>
                            @endforeach
                        </select>
                        <small class="form-hint">
                            Copies each variant and its price across when you save, so a new run isn't
                            retyping the whole size list. Adjust the prices afterwards — the earlier
                            run keeps what it actually sold at. Anything already listed below is left alone.
                        </small>
                    </div>
                </div>
            @endif

            @include('product.admin.variants')

            <div class="mb-4 d-flex align-items-center">
                <button type="submit" class="btn btn-primary">{{ $creating ? 'Create run' : 'Save run' }}</button>
                <a href="{{ route('products.edit', $product) }}" class="btn btn-link">Cancel</a>
            </div>
        </form>

        @unless($creating)
            <form method="POST" action="{{ route('products.runs.destroy', [$product, $run]) }}"
                  onsubmit="return confirm('Delete this run and everything on sale in it?')">
                @csrf @method('DELETE')
                <button class="btn btn-outline-danger btn-sm">Delete this run</button>
            </form>
        @endunless
    </div>
</div>
@endsection
