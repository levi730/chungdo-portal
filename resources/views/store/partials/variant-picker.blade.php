{{--
    Choosing a variant.

    With 23 variants a flat dropdown is unusable, so the picker cascades down the
    design's option axes: choose Item, and only the sizes that exist for that
    item are offered. That is why the size sets can differ per item (adult 7,
    youth 4, the bag none) without the buyer ever seeing a combination that was
    never printed.

    When a design has no axes, it falls back to one list of variants.
--}}
@php($axes = $product->option_names ?? [])
@php($variantData = $variants->map(fn ($v) => [
    'id' => $v->id,
    'name' => $v->displayName(),
    'price' => (float) $v->price,
    'options' => (object) ($v->options ?? []),
])->values())

<div x-data="{
        axes: @js(array_values($axes)),
        variants: @js($variantData),
        chosen: {},
        quantity: 1,

        // Values still reachable for an axis, given what is already chosen to
        // its left. Choosing 'Gym Bag' must not then offer 'XL'.
        valuesFor(axis) {
            const index = this.axes.indexOf(axis);
            const upstream = this.axes.slice(0, index);
            const seen = [];
            this.variants.forEach(v => {
                if (!upstream.every(a => !this.chosen[a] || v.options[a] === this.chosen[a])) return;
                const value = v.options[axis];
                if (value && !seen.includes(value)) seen.push(value);
            });
            return seen;
        },

        // Clear anything downstream when an upstream axis changes, so a stale
        // size can't survive switching item.
        choose(axis, value) {
            this.chosen[axis] = value;
            this.axes.slice(this.axes.indexOf(axis) + 1).forEach(a => delete this.chosen[a]);
        },

        get match() {
            if (!this.axes.length) {
                return this.variants.find(v => v.id === Number(this.chosen._flat)) || null;
            }
            return this.variants.find(v => this.axes.every(a => v.options[a] === this.chosen[a])) || null;
        },

        get price() {
            return this.match ? '$' + this.match.price.toFixed(2) : null;
        },
     }">

    <form method="POST" action="{{ route('store.cart.add') }}">
        @csrf
        <input type="hidden" name="product_variant_id" :value="match ? match.id : ''">

        @if(empty($axes))
            <div class="mb-3">
                <label class="form-label">Choose</label>
                <select class="form-select" x-model="chosen._flat">
                    <option value="">Select…</option>
                    @foreach($variants as $variant)
                        <option value="{{ $variant->id }}">
                            {{ $variant->displayName() }} — ${{ number_format($variant->price, 2) }}
                        </option>
                    @endforeach
                </select>
            </div>
        @else
            @foreach($axes as $axis)
                <div class="mb-3">
                    <label class="form-label">{{ $axis }}</label>
                    <select class="form-select"
                            @change="choose(@js($axis), $event.target.value)">
                        <option value="">Select {{ $axis }}…</option>
                        <template x-for="value in valuesFor(@js($axis))" :key="value">
                            <option :value="value" :selected="chosen[@js($axis)] === value" x-text="value"></option>
                        </template>
                    </select>
                </div>
            @endforeach
        @endif

        <div class="row align-items-end">
            <div class="col-4 mb-3">
                <label class="form-label">Quantity</label>
                <input type="number" name="quantity" class="form-control" min="1" max="100" x-model="quantity">
            </div>
            <div class="col-8 mb-3">
                <div class="h3 mb-0" x-show="price" x-text="price"></div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary" :disabled="!match">
            <span x-show="match">Add to cart</span>
            <span x-show="!match">Choose an option</span>
        </button>
    </form>
</div>
