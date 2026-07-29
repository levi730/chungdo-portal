<?php

namespace App\Mail;

use App\Models\AddonChangeRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class RefundRequested extends Mailable
{
    use Queueable, SerializesModels;

    public AddonChangeRequest $changeRequest;

    public string $studentName;

    public string $eventName;

    public float $amount;

    public array $lines;

    public string $reviewUrl;

    public function __construct(AddonChangeRequest $changeRequest)
    {
        $changeRequest->loadMissing(['registration.user', 'event.addons']);

        $this->changeRequest = $changeRequest;
        $this->studentName = $changeRequest->registration?->user?->full_name ?? 'A registrant';
        $this->eventName = $changeRequest->event?->name ?? 'an event';
        $this->amount = (float) $changeRequest->refund_amount;
        $this->lines = $changeRequest->summaryLines();
        $this->reviewUrl = $changeRequest->event?->slug
            ? route('event.refund-requests', $changeRequest->event->slug)
            : url('/');
    }

    public function build()
    {
        return $this->view('emails.events.refund-requested')
            ->subject('Refund request — '.$this->eventName);
    }
}
