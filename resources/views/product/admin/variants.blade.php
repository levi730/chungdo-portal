{{--
    The variants editor for ONE print run.

    The option axes ("Item, Size") belong to the design and are set on the
    product form; they arrive here as a fixed list. Each row below is one buyable
    SKU of this run, with its own price — which is why variants hang off the run
    and not the product.

    A row per size is what the schema wants (price lives on the row, and an order
    points at a specific row so the pick list can group by it) but the first run
    is 23 rows and nobody should type those. Hence "Quick add": it expands the
    values you give it rather than multiplying every axis blindly, because the
    size sets differ per item (adult 7, youth 4, a bag none) and a blind
    cross-product would invent rows that don't exist.
--}}
@php($submittedRows = old('variants'))
@php($axes = $product->option_names ?? [])

<div class="card mb-3"
     x-data="{
        axes: @js(array_values($axes)),
        // options is always an object, so x-model can write a new axis into a
        // row that predates it (PHP hands back [] for an empty map).
        rows: @js($submittedRows !== null ? array_values($submittedRows) : $variantRows)
                .map(r => ({ ...r, count: r.count ?? 0, enabled: !!r.enabled, options: { ...r.options } })),
        gen: {},
        genPrice: '',
        genNote: '',
        genError: '',

        addRow() {
            this.rows.push({ id: 0, name: '', sku: '', price: '0.00', enabled: true, sort_order: this.rows.length, options: {}, count: 0 });
        },

        // 'S, M, 2XL+2' -> [{value:'S',delta:0}, {value:'M',delta:0}, {value:'2XL',delta:2}]
        parseValues(text) {
            return (text || '').split(',').map(s => s.trim()).filter(Boolean).map(token => {
                const m = token.match(/^(.*?)\s*\+\s*(\d+(?:\.\d+)?)$/);
                return m ? { value: m[1].trim(), delta: parseFloat(m[2]) } : { value: token, delta: 0 };
            });
        },

        // Joined on a separator that cannot appear in a typed value, so
        // ['Adult T', 'Shirt S'] and ['Adult T-Shirt', 'S'] stay distinct.
        rowKey(options) {
            return this.axes.map(a => (options[a] || '').trim()).join('');
        },

        generate() {
            this.genNote = '';
            this.genError = '';

            const lists = this.axes.map(a => this.parseValues(this.gen[a]));

            if (!this.axes.length || lists.some(l => l.length === 0)) {
                this.genError = 'Give every option a value before generating.';
                return;
            }

            const base = parseFloat(this.genPrice) || 0;

            // Cross-product of exactly what was typed. One value in a box means
            // that axis is fixed for this batch, which is how you fill one item
            // at a time without inventing impossible combinations.
            let combos = [[]];
            lists.forEach(list => {
                const next = [];
                combos.forEach(c => list.forEach(v => next.push([...c, v])));
                combos = next;
            });

            let added = 0, skipped = 0;

            combos.forEach(combo => {
                const options = {};
                let price = base;
                combo.forEach((v, i) => { options[this.axes[i]] = v.value; price += v.delta; });

                if (this.rows.some(r => this.rowKey(r.options) === this.rowKey(options))) {
                    skipped++;
                    return;
                }

                this.rows.push({ id: 0, name: '', sku: '', price: price.toFixed(2), enabled: true, sort_order: this.rows.length, options, count: 0 });
                added++;
            });

            this.genNote = 'Added ' + added + (added === 1 ? ' variant' : ' variants')
                + (skipped ? ', skipped ' + skipped + ' already listed' : '') + '.';
        },
     }">
    <div class="card-header">
        <h3 class="card-title mb-0">What's on sale in this run</h3>
    </div>
    <div class="card-body">
        {{-- Marker so the save routine knows the editor was on the page and an
             empty rows array means "delete them", not "the form omitted them". --}}
        <input type="hidden" name="variants_present" value="1">

        @if(empty($axes))
            <div class="alert alert-warning">
                This design has no option axes yet. Set them on the
                <a href="{{ route('products.edit', $product) }}">product</a> first
                (for example <code>Item, Size</code>) and Quick add can fill this run in for you.
            </div>
        @else
            <div class="card card-sm bg-light mb-3">
                <div class="card-body">
                    <div class="fw-bold mb-2">Quick add</div>
                    <div class="row g-2 align-items-end">
                        <template x-for="opt in axes" :key="opt">
                            <div class="col-sm">
                                <label class="form-label small mb-1" x-text="opt"></label>
                                <input type="text" class="form-control form-control-sm" x-model="gen[opt]" :placeholder="opt">
                            </div>
                        </template>
                        <div class="col-sm-2">
                            <label class="form-label small mb-1">Base price</label>
                            <input type="number" step="0.01" min="0" class="form-control form-control-sm" x-model="genPrice">
                        </div>
                        <div class="col-sm-auto">
                            <button type="button" class="btn btn-sm btn-outline-primary" @click="generate()">Generate rows</button>
                        </div>
                    </div>
                    <small class="form-hint mt-2">
                        Put a comma-separated list in the box you want to expand and leave the others
                        as a single value — <code>Item: Adult Hoodie</code>,
                        <code>Size: XS, S, M, L, XL, 2XL+2, 3XL+3</code> makes seven rows at the base
                        price, with the last two $2 and $3 higher. <code>+n</code> after any value adds
                        to that row's price. Rows you already have are left alone.
                    </small>
                    <div class="text-success small mt-2" x-show="genNote" x-text="genNote"></div>
                    <div class="text-danger small mt-2" x-show="genError" x-text="genError"></div>
                </div>
            </div>
        @endif

        <div class="table-responsive">
            <table class="table table-vcenter table-sm">
                <thead>
                    <tr>
                        <template x-for="opt in axes" :key="opt">
                            <th x-text="opt"></th>
                        </template>
                        <th>Name</th>
                        <th>SKU</th>
                        <th style="width: 8rem">Price</th>
                        <th class="text-center">On sale</th>
                        <th style="width: 6rem">Sort</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <template x-for="(row, i) in rows" :key="i">
                        <tr>
                            <input type="hidden" :name="`variants[${i}][id]`" :value="row.id">

                            <template x-for="opt in axes" :key="opt">
                                <td>
                                    <input type="text" class="form-control form-control-sm"
                                           :name="`variants[${i}][options][${opt}]`"
                                           :placeholder="opt"
                                           x-model="row.options[opt]">
                                </td>
                            </template>

                            <td>
                                <input type="text" class="form-control form-control-sm"
                                       :name="`variants[${i}][name]`" x-model="row.name"
                                       placeholder="from the options">
                            </td>
                            <td>
                                <input type="text" class="form-control form-control-sm"
                                       :name="`variants[${i}][sku]`" x-model="row.sku">
                            </td>
                            <td>
                                <input type="number" step="0.01" min="0" class="form-control form-control-sm"
                                       :name="`variants[${i}][price]`" x-model="row.price">
                            </td>
                            <td class="text-center">
                                {{-- The checkbox carries no name; the hidden input posts the
                                     value, so an unchecked box still posts a 0. --}}
                                <input type="hidden" :name="`variants[${i}][enabled]`" :value="row.enabled ? 1 : 0">
                                <input type="checkbox" class="form-check-input m-0" x-model="row.enabled">
                            </td>
                            <td>
                                <input type="number" min="0" class="form-control form-control-sm"
                                       :name="`variants[${i}][sort_order]`" x-model="row.sort_order">
                            </td>
                            <td class="text-end">
                                <template x-if="row.count > 0">
                                    <span class="text-muted small text-nowrap" x-text="`${row.count} ordered`"></span>
                                </template>
                                <template x-if="row.count == 0">
                                    <button type="button" class="btn btn-ghost-danger btn-icon"
                                            @click="rows.splice(i, 1)" title="Remove variant">&times;</button>
                                </template>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>

        <template x-if="rows.length === 0">
            <p class="text-muted mb-2">Nothing on sale in this run yet.</p>
        </template>

        <button type="button" class="btn btn-sm btn-outline-primary mt-1" @click="addRow()">+ Add variant</button>

        <small class="form-hint mt-2">
            A variant that has already been ordered can be taken off sale, but not removed —
            the pick list still has to group those orders by it.
        </small>
    </div>
</div>
