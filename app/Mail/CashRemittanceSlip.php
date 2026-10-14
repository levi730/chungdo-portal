<?php

namespace App\Mail;

use App\Models\CashRemittance;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/** To the responsible instructor: a copy of the slip that goes in the envelope. */
class CashRemittanceSlip extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public CashRemittance $remittance)
    {
    }

    public function build()
    {
        return $this->view('emails.cash.slip', [
            'event' => $this->remittance->event,
            'slipUrl' => route('cash.slip', $this->remittance),
        ])->subject($this->remittance->reference.' — cash/check registrations for '.$this->remittance->event->name);
    }
}
