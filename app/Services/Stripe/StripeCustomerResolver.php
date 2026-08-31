<?php

namespace App\Services\Stripe;

use App\Models\StripeCustomer;
use App\Models\User;
use Stripe\StripeClient;

/**
 * Finds (or creates) a user's Stripe customer on a given Stripe account.
 *
 * Cashier keeps a single customer id on users.stripe_id, which is only valid on
 * the account Cashier is configured with (the association's). Passing that id
 * to another account fails with "No such customer", so customers are tracked
 * per account in stripe_customers.
 *
 * The association's rows are seeded lazily from users.stripe_id, so users who
 * already have a Cashier customer keep it and nothing is duplicated.
 */
class StripeCustomerResolver
{
    public function __construct(private StripeAccounts $accounts) {}

    /**
     * The user's customer id on $account, creating one at Stripe if needed.
     * Also attaches $paymentMethod and makes it the default when given.
     */
    public function resolve(User $user, string $account, ?string $paymentMethod = null): string
    {
        $account = $this->accounts->resolve($account);
        $stripe = new StripeClient($this->accounts->secret($account));

        $customerId = $this->existingId($user, $account, $stripe);

        if (! $customerId) {
            $customer = $stripe->customers->create([
                'email' => $user->email,
                'name' => trim(($user->firstname ?? '').' '.($user->lastname ?? '')) ?: null,
                'metadata' => ['portal_user_id' => $user->id],
            ]);
            $customerId = $customer->id;
        }

        StripeCustomer::updateOrCreate(
            ['user_id' => $user->id, 'account' => $account],
            ['stripe_customer_id' => $customerId],
        );

        // Keep Cashier's column in step for the account it belongs to, so the
        // rest of the Cashier-based code keeps working unchanged.
        if ($account === $this->accounts->default() && $user->stripe_id !== $customerId) {
            $user->forceFill(['stripe_id' => $customerId])->saveQuietly();
        }

        if ($paymentMethod) {
            $this->attach($stripe, $customerId, $paymentMethod);
        }

        return $customerId;
    }

    /**
     * A stored id, or Cashier's users.stripe_id when resolving the default
     * account for the first time. Ids that no longer exist at Stripe (a deleted
     * customer, or a value copied from another account) are discarded so a new
     * one is created instead of failing the charge.
     */
    private function existingId(User $user, string $account, StripeClient $stripe): ?string
    {
        $candidate = StripeCustomer::where('user_id', $user->id)
            ->where('account', $account)
            ->value('stripe_customer_id');

        if (! $candidate && $account === $this->accounts->default()) {
            $candidate = $user->stripe_id;
        }

        if (! $candidate) {
            return null;
        }

        try {
            $customer = $stripe->customers->retrieve($candidate, []);

            return ($customer->deleted ?? false) ? null : $customer->id;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private function attach(StripeClient $stripe, string $customerId, string $paymentMethod): void
    {
        try {
            $stripe->paymentMethods->attach($paymentMethod, ['customer' => $customerId]);
        } catch (\Throwable $e) {
            // Already attached to this customer is fine; anything else will
            // surface when the PaymentIntent is confirmed.
        }

        $stripe->customers->update($customerId, [
            'invoice_settings' => ['default_payment_method' => $paymentMethod],
        ]);
    }

    /** A SetupIntent on the given account, for collecting a card in the browser. */
    public function createSetupIntent(User $user, string $account): \Stripe\SetupIntent
    {
        $account = $this->accounts->resolve($account);
        $stripe = new StripeClient($this->accounts->secret($account));

        return $stripe->setupIntents->create([
            'customer' => $this->resolve($user, $account),
            'payment_method_types' => ['card'],
            'usage' => 'on_session',
        ]);
    }
}
