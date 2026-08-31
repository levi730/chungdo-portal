<?php

namespace App\Services\Stripe;

use App\Models\Event;
use RuntimeException;

/**
 * Resolves which Stripe account a piece of work belongs to, and hands back that
 * account's credentials.
 *
 * Accounts are declared in config('services.stripe.accounts'). An event names
 * its account in events.stripe_account; anything without an event (or with an
 * unknown/unconfigured value) falls back to the default account, which is the
 * association's.
 *
 * Every Stripe call for event money must set the key from here rather than
 * reading config('services.stripe.secret') directly — that constant is the
 * association's and would silently charge the wrong account.
 */
class StripeAccounts
{
    /** All accounts that actually have a secret configured: slug => label. */
    public function options(): array
    {
        $options = [];

        foreach ((array) config('services.stripe.accounts', []) as $slug => $account) {
            if (filled($account['secret'] ?? null)) {
                $options[$slug] = $account['label'] ?? $slug;
            }
        }

        return $options;
    }

    public function default(): string
    {
        return (string) config('services.stripe.default_account', 'association');
    }

    /** Normalize a slug: unknown or unconfigured falls back to the default. */
    public function resolve(?string $slug): string
    {
        return $slug && array_key_exists($slug, $this->options())
            ? $slug
            : $this->default();
    }

    public function forEvent(?Event $event): string
    {
        return $this->resolve($event?->stripe_account);
    }

    public function label(?string $slug): string
    {
        return $this->options()[$this->resolve($slug)] ?? $this->resolve($slug);
    }

    public function secret(?string $slug): string
    {
        return $this->credential($slug, 'secret');
    }

    public function publishableKey(?string $slug): string
    {
        return $this->credential($slug, 'key');
    }

    /** Secret for an event's account. */
    public function secretForEvent(?Event $event): string
    {
        return $this->secret($this->forEvent($event));
    }

    /** Publishable key for an event's account (goes to the browser). */
    public function publishableKeyForEvent(?Event $event): string
    {
        return $this->publishableKey($this->forEvent($event));
    }

    /**
     * Every configured webhook signing secret: slug => secret. The webhook
     * endpoint tries each, because one URL can receive events from more than
     * one account.
     */
    public function webhookSecrets(): array
    {
        $secrets = [];

        foreach ((array) config('services.stripe.accounts', []) as $slug => $account) {
            if (filled($account['webhook_secret'] ?? null)) {
                $secrets[$slug] = $account['webhook_secret'];
            }
        }

        return $secrets;
    }

    private function credential(?string $slug, string $which): string
    {
        $slug = $this->resolve($slug);
        $value = config("services.stripe.accounts.{$slug}.{$which}");

        if (blank($value)) {
            throw new RuntimeException("Stripe account '{$slug}' has no {$which} configured.");
        }

        return $value;
    }
}
