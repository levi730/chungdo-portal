<?php

namespace App\Services\Stripe;

/**
 * Anything whose money lands in a named Stripe account.
 *
 * The portal transacts on more than one Stripe account, so every model that can
 * be charged for has to say which one. Implementing this lets StripeAccounts
 * resolve credentials for the model directly — see StripeAccounts::for() —
 * instead of the service growing a *ForEvent / *ForProduct pair per model.
 *
 * Returning null means "the default account" (the association's).
 */
interface ChargedToStripeAccount
{
    public function stripeAccountSlug(): ?string;
}
