<?php

namespace App\Console\Commands;

use App\Models\ProductOrder;
use App\Services\Store\ProductOrderFulfiller;
use App\Services\Stripe\StripeAccounts;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Stripe\Checkout\Session;
use Stripe\PaymentIntent;
use Stripe\Stripe;

/**
 * Asks Stripe what happened to orders that reached it but never completed.
 *
 * This is the backstop that does not depend on a webhook arriving at all —
 * which matters because Stripe disables endpoints after sustained delivery
 * failures, and neither existing payment flow in this portal can self-heal from
 * that (docs/payment-flow-pattern.md). The store ships one from the start.
 *
 * Safe to run repeatedly: it only ever calls the idempotent fulfiller, and it
 * only looks at orders that actually reached Stripe.
 */
class ReconcileStoreOrders extends Command
{
    protected $signature = 'store:reconcile-orders
                            {--minutes=15 : How old a pending order must be before we ask about it}
                            {--limit=200 : Most orders to examine in one pass}
                            {--dry-run : Report what would happen without changing anything}';

    protected $description = 'Reconcile pending store orders against Stripe';

    public function handle(ProductOrderFulfiller $fulfiller, StripeAccounts $accounts): int
    {
        $minutes = (int) $this->option('minutes');
        $dryRun = (bool) $this->option('dry-run');

        $orders = ProductOrder::stalePending($minutes)
            ->orderBy('id')
            ->limit((int) $this->option('limit'))
            ->get();

        if ($orders->isEmpty()) {
            $this->info('Nothing to reconcile.');

            return self::SUCCESS;
        }

        $this->info($orders->count().' pending order(s) older than '.$minutes.' minutes.');

        $paid = $failed = $unresolved = 0;

        foreach ($orders as $order) {
            try {
                Stripe::setApiKey($accounts->secret($order->stripe_account));
                [$outcome, $amount] = $this->askStripe($order);
            } catch (\Throwable $e) {
                // A credential or network problem must not abort the sweep —
                // the next order may be on a different account.
                Log::error('store:reconcile-orders could not query Stripe', [
                    'product_order_id' => $order->id,
                    'error' => $e->getMessage(),
                ]);
                $this->warn("  #{$order->id} {$order->reference}: could not query Stripe — {$e->getMessage()}");
                $unresolved++;
                continue;
            }

            $line = "  #{$order->id} {$order->reference}: {$outcome}";

            if ($outcome === 'paid') {
                $paid++;
                $this->line($dryRun ? $line.' (dry run)' : $line);
                if (! $dryRun) {
                    $fulfiller->reconcileSucceeded(
                        (string) ($order->stripe_payment_intent_id ?? $order->stripe_checkout_session_id),
                        $order->id,
                        $amount
                    );
                }
            } elseif ($outcome === 'failed') {
                $failed++;
                $this->line($dryRun ? $line.' (dry run)' : $line);
                if (! $dryRun) {
                    $fulfiller->markFailed(
                        (string) ($order->stripe_payment_intent_id ?? ''),
                        $order->id
                    );
                }
            } else {
                // Still genuinely in flight, or awaiting the buyer. Leave it.
                $unresolved++;
                $this->line($line);
            }
        }

        $this->info("Done. paid={$paid} failed={$failed} left alone={$unresolved}");

        return self::SUCCESS;
    }

    /**
     * What Stripe says about this order. The intent is authoritative when we
     * have one; otherwise the Checkout Session, which is all a guest order has
     * until the buyer actually pays.
     *
     * @return array{0: string, 1: float} [paid|failed|pending, amount]
     */
    private function askStripe(ProductOrder $order): array
    {
        if ($order->stripe_payment_intent_id) {
            $intent = PaymentIntent::retrieve($order->stripe_payment_intent_id);

            return match ($intent->status) {
                'succeeded' => ['paid', ($intent->amount_received ?? 0) / 100],
                'canceled' => ['failed', 0.0],
                // requires_payment_method after an attempt means the card was
                // declined and nothing is in flight.
                'requires_payment_method' => ['failed', 0.0],
                default => ['pending ('.$intent->status.')', 0.0],
            };
        }

        if ($order->stripe_checkout_session_id) {
            $session = Session::retrieve($order->stripe_checkout_session_id);

            if (($session->payment_status ?? null) === 'paid') {
                return ['paid', ($session->amount_total ?? 0) / 100];
            }

            // An expired session can never be paid; the buyer abandoned it.
            if (($session->status ?? null) === 'expired') {
                return ['failed', 0.0];
            }

            return ['pending (session '.($session->status ?? 'unknown').')', 0.0];
        }

        // stalePending() only returns rows carrying one id or the other.
        return ['pending (no Stripe reference)', 0.0];
    }
}
