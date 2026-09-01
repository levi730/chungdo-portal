@extends('layouts.dashboard')

@section('page-title')
    {{ $creating ? 'Add Product' : 'Edit: '.$product->name }}
@endsection

@section('content')
<div class="container-xl">
    <div class="content">
        {{-- The heading comes from @section('page-title') above; the events form
             does the same and repeating it here would print it twice. --}}
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif

        @if($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- Existing images with per-item delete (separate forms — can't nest in the main form) --}}
        @unless($creating)
            @if($product->getMedia('product-images')->count())
                <div class="card mb-3">
                    <div class="card-header"><h3 class="card-title mb-0">Current images</h3></div>
                    <div class="card-body">
                        @include('partials.product.image-focus')
                    </div>
                </div>
            @endif
        @endunless

        <form method="POST"
              action="{{ $creating ? route('products.store') : route('products.update', $product) }}"
              enctype="multipart/form-data">
            @csrf
            @unless($creating) @method('PUT') @endunless

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Details</h3></div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-8 mb-3">
                            <label class="form-label required">Name</label>
                            <input type="text" name="name" class="form-control" value="{{ old('name', $product->name) }}" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                @foreach(\App\Models\Product::STATUSES as $value => $label)
                                    <option value="{{ $value }}" @selected(old('status', $product->status ?? \App\Models\Product::STATUS_DRAFT) === $value)>{{ $label }}</option>
                                @endforeach
                            </select>
                            <small class="form-hint">Only an Active product inside its order window can be bought.</small>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">URL slug</label>
                        <input type="text" name="slug" class="form-control" value="{{ old('slug', $product->slug) }}" placeholder="auto-generated from name">
                        <small class="form-hint">The public address: /store/&lt;slug&gt;</small>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" class="form-control" rows="5">{{ old('description', $product->description) }}</textarea>
                        <small class="form-hint">Markdown.</small>
                    </div>

                    @php($stripeLocked = ! $creating && $product->hasPayments())
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Payments go to</label>
                            <select name="stripe_account" class="form-select" @disabled($stripeLocked)>
                                @foreach($stripeAccounts->options() as $slug => $label)
                                    <option value="{{ $slug }}" @selected(old('stripe_account', $product->stripe_account ?? $stripeAccounts->default()) === $slug)>{{ $label }}</option>
                                @endforeach
                            </select>
                            @if($stripeLocked)
                                {{-- A disabled select posts nothing; re-post the current value so
                                     the server-side check has something to compare. --}}
                                <input type="hidden" name="stripe_account" value="{{ $product->stripe_account }}">
                                <small class="form-hint text-warning">
                                    Locked — this product has taken payments. Refunds have to be issued on the
                                    account that took the charge, so it can't be moved.
                                </small>
                            @else
                                <small class="form-hint">
                                    Which Stripe account this product's money lands in. Can't be changed once
                                    the first order is taken.
                                </small>
                            @endif
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">Max per order</label>
                            <input type="number" name="max_per_order" class="form-control" min="1" value="{{ old('max_per_order', $product->max_per_order) }}" placeholder="no limit">
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label">List order</label>
                            <input type="number" name="sort_order" class="form-control" min="0" value="{{ old('sort_order', $product->sort_order ?? 0) }}">
                            <small class="form-hint">Lower sits earlier in the store list.</small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Options</h3></div>
                <div class="card-body">
                    <label class="form-label">Option axes</label>
                    <input type="text" name="option_names" class="form-control"
                           value="{{ old('option_names') !== null
                                ? (is_array(old('option_names')) ? implode(', ', old('option_names')) : old('option_names'))
                                : implode(', ', $product->option_names ?? []) }}"
                           placeholder="Item, Size">
                    <small class="form-hint">
                        Comma separated, and optional. Only list things the buyer actually chooses —
                        if every shirt is the same colour, that belongs in the description, not here.
                        These label the columns when you fill in a run's price list.
                    </small>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Home page</h3></div>
                <div class="card-body">
                    <div class="mb-3">
                        <label class="form-check">
                            <input type="hidden" name="highlighted" value="0">
                            <input type="checkbox" name="highlighted" value="1" class="form-check-input" @checked(old('highlighted', $product->highlighted))>
                            <span class="form-check-label">Feature this product on the home page</span>
                        </label>
                    </div>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Home page order</label>
                            <input type="number" name="highlight_order" class="form-control" min="0" max="65535"
                                   value="{{ old('highlight_order', $product->highlight_order ?? 0) }}">
                            <small class="form-hint">
                                Higher sits higher on the page. Leave at 0 unless you want this product
                                above another featured one. Unlike events, nothing appears on the home page
                                unless it is featured here.
                            </small>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card mb-3">
                <div class="card-header"><h3 class="card-title mb-0">Add images</h3></div>
                <div class="card-body">
                    <label class="form-label">Product images</label>
                    <input type="file" name="images[]" class="form-control" accept="image/*" multiple>
                </div>
            </div>

            <div class="mb-4">
                <button type="submit" class="btn btn-primary">{{ $creating ? 'Create product' : 'Save changes' }}</button>
                <a href="{{ route('products.index') }}" class="btn btn-link">Cancel</a>
            </div>
        </form>

        {{-- Runs sit outside the product form: each has its own page, because a
             run owns a whole price list and several of those on one screen would
             be unreadable. Their edit/delete controls are their own forms too. --}}
        @unless($creating)
            <div class="card mb-4">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <h3 class="card-title mb-0">Print runs</h3>
                    <a href="{{ route('products.runs.create', $product) }}" class="btn btn-sm btn-primary">+ Add run</a>
                </div>
                @if($runs->isEmpty())
                    <div class="card-body text-muted">
                        No runs yet. A design needs an open run before anything can be bought —
                        the run carries the order window, the prices and the pickup wording.
                    </div>
                @else
                    <div class="table-responsive">
                        <table class="table table-vcenter">
                            <thead>
                                <tr>
                                    <th>Run</th>
                                    <th>Opens</th>
                                    <th>Closes</th>
                                    <th>Arrives</th>
                                    <th class="text-center">On sale</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($runs as $run)
                                    <tr>
                                        <td>
                                            {{ $run->name }}
                                            @if($run->isOpen())
                                                <span class="badge bg-green-lt ms-1">Open</span>
                                            @elseif($run->opensLater())
                                                <span class="badge bg-azure-lt ms-1">Scheduled</span>
                                            @else
                                                <span class="badge bg-secondary-lt ms-1">Closed</span>
                                            @endif
                                        </td>
                                        <td>{{ optional($run->opens_at)->format('M j, Y g:ia') ?? '—' }}</td>
                                        <td>{{ optional($run->closes_at)->format('M j, Y g:ia') ?? '—' }}</td>
                                        <td>{{ optional($run->expected_arrival_at)->format('M j, Y') ?? '—' }}</td>
                                        <td class="text-center">{{ $run->variants_count }}</td>
                                        <td class="text-end">
                                            <a href="{{ route('products.runs.edit', [$product, $run]) }}" class="btn btn-sm btn-outline-primary">Edit</a>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
        @endunless
    </div>
</div>
@endsection
