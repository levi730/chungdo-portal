<?php

namespace App\Http\Requests;

use App\Models\ProductRun;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validation for one print run and its variants.
 */
class ProductRunRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('store.manage') ?? false;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:255',
            'opens_at' => 'nullable|date',
            'closes_at' => 'nullable|date|after:opens_at',
            'expected_arrival_at' => 'nullable|date',
            'pickup_note' => 'nullable|string|max:255',
            'sort_order' => 'integer|min:0',

            // Variants arrive from the repeater. A blank name is allowed — the
            // sync derives one from the option values.
            'variants' => 'nullable|array',
            'variants.*.id' => 'nullable|integer',
            'variants.*.name' => 'nullable|string|max:255',
            'variants.*.sku' => 'nullable|string|max:255',
            'variants.*.price' => 'nullable|numeric|min:0|max:999999.99',
            'variants.*.sort_order' => 'nullable|integer|min:0',
            'variants.*.options' => 'nullable|array',
            'variants.*.options.*' => 'nullable|string|max:100',
        ];
    }

    public function messages(): array
    {
        return [
            'closes_at.after' => 'The order window has to close after it opens.',
        ];
    }

    /**
     * Only one run of a design may be open at a time. Two live windows would
     * mean the store had to ask the buyer which run they were ordering into,
     * and the pick list could no longer name one arrival date per line.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            if ($validator->errors()->hasAny(['opens_at', 'closes_at'])) {
                return; // the dates aren't usable yet
            }

            $product = $this->route('product');
            $current = $this->route('run');

            $candidate = new ProductRun([
                'opens_at' => $this->input('opens_at') ?: null,
                'closes_at' => $this->input('closes_at') ?: null,
            ]);

            $clash = $product->runs()
                ->when($current, fn ($q) => $q->whereKeyNot($current->id))
                ->get()
                ->first(fn (ProductRun $other) => $candidate->overlaps($other));

            if ($clash) {
                $validator->errors()->add(
                    'opens_at',
                    'These dates overlap the "'.$clash->name.'" run. Only one run of a design can be open at a time.'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'sort_order' => (int) $this->input('sort_order', 0),
        ]);
    }
}
