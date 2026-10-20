<?php

namespace App\Services\Sendportal;

use Illuminate\Support\Facades\Log;
use Postmark\Models\PostmarkException;
use Sendportal\Base\Models\Campaign;
use Sendportal\Base\Models\Subscriber;
use Sendportal\Base\Models\UnsubscribeEventType;
use Sendportal\Base\Pipelines\Campaigns\CreateMessages as BaseCreateMessages;

/**
 * Sendportal's campaign pipeline step, made to survive one bad recipient.
 *
 * The queue is `sync` in production, so the send happens inside this loop and
 * the package lets any exception escape it. One address Postmark refuses then
 * stops the campaign at that subscriber: everyone after them is never reached
 * and the campaign stays "Sending" forever. That cut off about half the list on
 * every send-to-all campaign from late 2024 to October 2026.
 *
 * Here a failed send is logged and the loop carries on. The message row is left
 * unsent. Postmark's "inactive recipient" (406: a hard bounce, complaint or
 * manual suppression) can never succeed, so that subscriber is also marked
 * unsubscribed as a bounce, and later campaigns skip them.
 *
 * Bound over the package class in AppServiceProvider; the pipeline resolves its
 * pipes through the container.
 */
class CreateMessages extends BaseCreateMessages
{
    public const POSTMARK_INACTIVE_RECIPIENT = 406;

    protected function dispatch(Campaign $campaign, Subscriber $subscriber): void
    {
        try {
            parent::dispatch($campaign, $subscriber);
        } catch (\Throwable $e) {
            $inactive = $e instanceof PostmarkException
                && (int) $e->getPostmarkApiErrorCode() === self::POSTMARK_INACTIVE_RECIPIENT;

            Log::warning('Campaign send failed; skipping recipient', [
                'campaign_id' => $campaign->id,
                'subscriber_id' => $subscriber->id,
                'email' => $subscriber->email,
                'unsubscribed' => $inactive,
                'exception' => $e->getMessage(),
            ]);

            if ($inactive) {
                $subscriber->forceFill([
                    'unsubscribed_at' => now(),
                    'unsubscribe_event_id' => UnsubscribeEventType::BOUNCE,
                ])->save();
            }
        }
    }
}
