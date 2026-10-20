<?php

use App\Models\User;
use App\Services\Sendportal\CreateMessages;
use Postmark\Models\PostmarkException;
use Sendportal\Base\Models\Campaign;
use Sendportal\Base\Models\Message;
use Sendportal\Base\Models\Subscriber;
use Sendportal\Base\Models\UnsubscribeEventType;
use Sendportal\Base\Pipelines\Campaigns\CreateMessages as BaseCreateMessages;
use Sendportal\Base\Services\Messages\DispatchMessage;

// A workspace no real subscriber is in, so send_to_all sees only these rows.
const TEST_WORKSPACE = 990001;

function testSubscriber(string $email): Subscriber
{
    return Subscriber::forceCreate([
        'workspace_id' => TEST_WORKSPACE,
        'hash' => (string) Str::uuid(),
        'email' => $email,
        'first_name' => 'Test',
        'last_name' => 'Subscriber',
    ]);
}

function testCampaign(): Campaign
{
    return (new Campaign)->forceFill([
        'id' => 990001,
        'workspace_id' => TEST_WORKSPACE,
        'send_to_all' => true,
        'save_as_draft' => false,
        'subject' => 'Test',
        'from_name' => 'Test',
        'from_email' => 'test@example.com',
    ]);
}

/** Run the campaign step with a sender that refuses $refuse with $exception. */
function sendCampaign(Campaign $campaign, string $refuse, Throwable $exception): void
{
    test()->mock(DispatchMessage::class, function ($mock) use ($refuse, $exception) {
        $mock->shouldReceive('handle')->andReturnUsing(function (Message $message) use ($refuse, $exception) {
            if ($message->recipient_email === $refuse) {
                throw $exception;
            }
            $message->forceFill(['sent_at' => now()])->save();

            return $message->message_id;
        });
    });

    app(BaseCreateMessages::class)->handle($campaign, fn ($c) => $c);
}

function inactiveRecipient(): PostmarkException
{
    $e = new PostmarkException;
    $e->message = 'You tried to send to recipient(s) that have been marked as inactive.';
    $e->setPostmarkApiErrorCode(CreateMessages::POSTMARK_INACTIVE_RECIPIENT);

    return $e;
}

it('resolves the package pipeline step to the app override', function () {
    expect(app(BaseCreateMessages::class))->toBeInstanceOf(CreateMessages::class);
});

it('keeps sending past a recipient Postmark refuses, and unsubscribes them as a bounce', function () {
    $before = testSubscriber('before@example.com');
    $bad = testSubscriber('suppressed@example.com');
    $after = testSubscriber('after@example.com');
    $campaign = testCampaign();

    sendCampaign($campaign, 'suppressed@example.com', inactiveRecipient());

    $sent = Message::where('source_id', $campaign->id)->whereNotNull('sent_at')->pluck('subscriber_id');
    expect($sent->sort()->values()->all())->toBe([$before->id, $after->id]);

    expect(Message::where('source_id', $campaign->id)->where('subscriber_id', $bad->id)->value('sent_at'))->toBeNull();
    expect($bad->fresh()->unsubscribed_at)->not->toBeNull();
    expect($bad->fresh()->unsubscribe_event_id)->toBe(UnsubscribeEventType::BOUNCE);
});

it('keeps sending past any other failure but leaves that subscriber subscribed', function () {
    testSubscriber('one@example.com');
    $flaky = testSubscriber('flaky@example.com');
    testSubscriber('three@example.com');
    $campaign = testCampaign();

    sendCampaign($campaign, 'flaky@example.com', new RuntimeException('connection reset'));

    expect(Message::where('source_id', $campaign->id)->whereNotNull('sent_at')->count())->toBe(2);
    expect($flaky->fresh()->unsubscribed_at)->toBeNull();
});

it('does not subscribe the address of an account being deleted', function () {
    $user = User::factory()->create(['email' => 'deleted-bot@example.com', 'mailings' => true]);

    // A sign-up has no subscriber until its first update.
    Subscriber::where('email', 'deleted-bot@example.com')->delete();

    User::findOrFail($user->id)->delete();

    expect(Subscriber::where('email', 'deleted-bot@example.com')->exists())->toBeFalse();
});

it('still removes the subscriber of an account being deleted', function () {
    $user = User::factory()->create(['email' => 'leaving@example.com', 'mailings' => true]);
    expect(Subscriber::where('email', 'leaving@example.com')->exists())->toBeTrue();

    $user->delete();

    expect(Subscriber::where('email', 'leaving@example.com')->exists())->toBeFalse();
});
