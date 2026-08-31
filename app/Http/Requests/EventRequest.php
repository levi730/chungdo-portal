<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class EventRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('event.manage') ?? false;
    }

    public function rules(): array
    {
        // On update the route model is bound as {event}; ignore its slug for uniqueness.
        $eventId = $this->route('event')?->id;

        return [
            'name' => 'required|string|max:255',
            'type' => ['nullable', Rule::in(array_keys(\App\Models\Event::TYPES))],
            'host_school_id' => 'nullable|exists:schools,id',
            'stripe_account' => ['nullable', Rule::in(array_keys(app(\App\Services\Stripe\StripeAccounts::class)->options()))],
            'startdatetime' => 'nullable|date',
            'enddatetime' => 'nullable|date|after_or_equal:startdatetime',
            'location' => 'nullable|string|max:2000',
            'details' => 'nullable|string',
            'slug' => [
                'nullable', 'string', 'max:255',
                Rule::unique('events', 'slug')->ignore($eventId)->whereNull('deleted_at'),
            ],
            'map_url' => 'nullable|string|max:2000',
            'minimum_rank_id' => 'nullable|exists:ranks,id',
            'require_ticket' => 'boolean',
            'forms.*' => 'nullable|file|mimes:pdf|max:20480',
            'slideshow.*' => 'nullable|file|image|max:20480',
        ];
    }

    /**
     * The Stripe account is locked once the event has taken money — refunds
     * have to go back through the account that took the charge. The select is
     * disabled in the form; this is the server-side half of that rule.
     */
    public function withValidator($validator): void
    {
        $validator->after(function ($validator) {
            $event = $this->route('event');

            if (! $event || ! $this->has('stripe_account') || ! $event->hasPayments()) {
                return;
            }

            $accounts = app(\App\Services\Stripe\StripeAccounts::class);

            if ($accounts->resolve($this->input('stripe_account')) !== $accounts->resolve($event->stripe_account)) {
                $validator->errors()->add(
                    'stripe_account',
                    'This event has already taken payments, so its Stripe account cannot be changed.'
                );
            }
        });
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'require_ticket' => $this->boolean('require_ticket'),
            'slug' => $this->filled('slug') ? \Illuminate\Support\Str::slug($this->input('slug')) : \Illuminate\Support\Str::slug($this->input('name')),
        ]);
    }
}
