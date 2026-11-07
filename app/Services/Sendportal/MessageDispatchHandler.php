<?php

namespace App\Services\Sendportal;

use Illuminate\Support\Facades\Log;
use Postmark\Models\PostmarkException;
use Sendportal\Base\Events\MessageDispatchEvent;
use Sendportal\Base\Listeners\MessageDispatchHandler as BaseMessageDispatchHandler;
use Sendportal\Base\Models\UnsubscribeEventType;

/**
 * The send itself, which runs on the sendportal-message-dispatch workers.
 *
 * Postmark's "inactive recipient" (406: a hard bounce, complaint or manual
 * suppression) can never succeed, so that subscriber is marked unsubscribed as
 * a bounce and later campaigns skip them. The message row is left unsent. Any
 * other failure is rethrown, which puts the job in failed_jobs.
 *
 * Bound over the package class in AppServiceProvider.
 */
class MessageDispatchHandler extends BaseMessageDispatchHandler
{
    public const POSTMARK_INACTIVE_RECIPIENT = 406;

    public function handle(MessageDispatchEvent $event): void
    {
        try {
            parent::handle($event);
        } catch (PostmarkException $e) {
            if ((int) $e->getPostmarkApiErrorCode() !== self::POSTMARK_INACTIVE_RECIPIENT) {
                throw $e;
            }

            Log::warning('Campaign recipient is inactive; unsubscribing', [
                'message_id' => $event->message->id,
                'subscriber_id' => $event->message->subscriber_id,
                'email' => $event->message->recipient_email,
                'exception' => $e->getMessage(),
            ]);

            $event->message->subscriber?->forceFill([
                'unsubscribed_at' => now(),
                'unsubscribe_event_id' => UnsubscribeEventType::BOUNCE,
            ])->save();
        }
    }
}
