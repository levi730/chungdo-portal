<?php

namespace App\Mail;

use App\Models\CashRemittance;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * To a family: their students were registered by an instructor, and payment
 * is pending in cash or check. One per household, naming only their students.
 */
class CashRegistrationPending extends Mailable
{
    use Queueable, SerializesModels;

    public $event;

    /**
     * @param  array  $students  this household's slip lines
     */
    public function __construct(
        public CashRemittance $remittance,
        public User $recipient,
        public array $students,
        public ?string $qr_image = null,
    ) {
        $this->event = $remittance->event;
    }

    public function amount(): float
    {
        return round(collect($this->students)->sum('amount'), 2);
    }

    public function build()
    {
        return $this->view('emails.cash.registration-pending', ['amount' => $this->amount()])
            ->subject('Event Registered: '.$this->event->name.' (payment pending)');
    }
}
