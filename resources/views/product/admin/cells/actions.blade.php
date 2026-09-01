{{--
    Real form POSTs rather than Livewire actions, so archive/restore work
    through the same routes the tests exercise and survive a JS-less page.
--}}
<div class="text-end text-nowrap">
    @if($product->trashed())
        <form method="POST" action="{{ route('products.restore', $product->id) }}" class="d-inline">
            @csrf
            <button class="btn btn-sm btn-outline-success">Restore</button>
        </form>
    @else
        <a href="{{ route('products.edit', $product) }}" class="btn btn-sm btn-outline-primary">Edit</a>
        <form method="POST" action="{{ route('products.destroy', $product) }}" class="d-inline"
              onsubmit="return confirm('Archive this product? Its orders are kept and it can be restored.')">
            @csrf @method('DELETE')
            <button class="btn btn-sm btn-outline-danger">Archive</button>
        </form>
    @endif
</div>
