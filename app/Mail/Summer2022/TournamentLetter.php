<?php

namespace App\Mail\Summer2022;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TournamentLetter extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct()
    {

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.summer-2022.tournament-letter')
            ->subject('Summer 2022 Sparring Tournament Details!')
            ->attach(resource_path('events/summer-2022/TournamentWaiver.pdf'));
    }
}
