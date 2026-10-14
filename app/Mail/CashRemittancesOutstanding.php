<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

/** To whoever receives payments: remittances still not turned in after their event. */
class CashRemittancesOutstanding extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Collection $remittances)
    {
    }

    public function build()
    {
        return $this->view('emails.cash.outstanding', ['listUrl' => route('cash.remittances')])
            ->subject($this->remittances->count().' cash/check remittance(s) still outstanding');
    }
}
