<?php

namespace App\Http\Requests;

use App\Models\Product;
use App\Services\Stripe\StripeAccounts;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Validation for the product admin form — the store's counterpart to
 * EventRequest, and deliberately the same shape.
 */
class ProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('store.manage') ?? false;
    }

    public function rules(): array
    {
        // On update the route model is bound as {product}; ignore its own slug.
        $productId = $this->route('product')?->id;

        return [
            'name' => 'required|string|max:255',
            'status' => ['required', Rule::in(array_keys(Product::STATUSES))],
            'stripe_account' => ['nullable', Rule::in(array_keys(app(StripeAccounts::class)->options()))],
            'description' => 'nullable|string',
            'max_per_order' => 'nullable|integer|min:1|max:1000',

            // products.slug is unique at the database level with no deleted_at
            // exclusion, so an archived product keeps holding its slug. Match
            // that here rather than letting the DB throw.
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('products', 'slug')->ignore($productId),
            ],

            'highlighted' => 'boolean',
            'highlight_order' => 'integer|min:0|max:65535',
            'sort_order' => 'integer|min:0',

            'option_names' => 'nullable|array|max:5',
            'option_names.*' => 'nullable|string|max:50',

            // Variants belong to a run, not to the design — see ProductRunRequest.

            'images.*' => 'nullable|file|image|max:20480',
        ];
    }

    /**
     * The Stripe account is locked once a charge has been attempted for this
     * product — a refund has to go back through the account that took it. The
     * select is disabled in the form; this is the server-side half of that rule.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $product = $this->route('product');

            if (! $product || ! $this->has('stripe_account') || ! $product->hasPayments()) {
                return;
            }

            $accounts = app(StripeAccounts::class);

            // resolve() both sides so a stale slug already on the record
            // compares equal to the default instead of false-tripping.
            if ($accounts->resolve($this->input('stripe_account')) !== $accounts->resolve($product->stripe_account)) {
                $validator->errors()->add(
                    'stripe_account',
                    'This product has already taken payments, so its Stripe account cannot be changed.'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'highlighted' => $this->boolean('highlighted'),
            'highlight_order' => (int) $this->input('highlight_order', 0),
            'sort_order' => (int) $this->input('sort_order', 0),
            'slug' => $this->filled('slug')
                ? Str::slug($this->input('slug'))
                : Str::slug($this->input('name')),
            // The form posts the option axes as one comma-separated field —
            // "Item, Color, Size" — because they are a short fixed list, not
            // something worth a second repeater.
            'option_names' => $this->optionNames(),
        ]);
    }

    /**
     * The option axis names, trimmed and de-duplicated. Null when there are
     * none, so the column stays null rather than holding an empty array.
     *
     * @return string[]|null
     */
    public function optionNames(): ?array
    {
        $raw = $this->input('option_names');

        $names = collect(is_array($raw) ? $raw : explode(',', (string) $raw))
            ->map(fn ($n) => trim((string) $n))
            ->filter()
            ->unique()
            ->values()
            ->all();

        return $names ?: null;
    }
}
