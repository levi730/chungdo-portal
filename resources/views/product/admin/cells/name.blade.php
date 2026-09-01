<span @class(['text-muted' => $product->trashed()])>{{ $product->name }}</span>
@if($product->trashed())
    <span class="badge bg-secondary text-white ms-1">Archived</span>
@endif
@if($product->highlighted)
    <span class="badge bg-azure-lt ms-1" title="Featured on the home page (order {{ $product->highlight_order }})">Featured</span>
@endif
