<?php

namespace App\Services\Sendportal;

use Illuminate\Support\Facades\Log;
use Sendportal\Base\Models\Campaign;
use Sendportal\Base\Models\Subscriber;
use Sendportal\Base\Pipelines\Campaigns\CreateMessages as BaseCreateMessages;

/**
 * Sendportal's campaign pipeline step, made to survive one bad recipient.
 *
 * On a sync queue the send happens inside this loop and the package lets any
 * exception escape it, so one refused address stops the campaign at that
 * subscriber: everyone after them is never reached and the campaign stays
 * "Sending" forever. That cut off about half the list on every send-to-all
 * campaign from late 2024 to October 2026, while production's queue was sync.
 *
 * Production now queues the sends, but a failure here must still not end the
 * campaign, so it is logged and the loop carries on. Unsubscribing a recipient
 * Postmark calls inactive belongs to MessageDispatchHandler, where the send runs.
 *
 * Bound over the package class in AppServiceProvider; the pipeline resolves its
 * pipes through the container.
 */
class CreateMessages extends BaseCreateMessages
{
    protected function dispatch(Campaign $campaign, Subscriber $subscriber): void
    {
        try {
            parent::dispatch($campaign, $subscriber);
        } catch (\Throwable $e) {
            Log::warning('Campaign send failed; skipping recipient', [
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriber->id,
                'email' => $subscriber->email,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
