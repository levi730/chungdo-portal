<?php

namespace App\Mail\Winter2023;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class TournamentLetter extends Mailable
{
    use Queueable, SerializesModels;

    private $qr_image;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($image_path)
    {
        $this->qr_image = $image_path;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.winter-2023.tournament-letter', ['qr_image' => $this->qr_image])
            ->subject('Winter 2023 Sparring Tournament Details!')
            ->attach(resource_path('events/winter-2023/TournamentWaiver.pdf'));
    }
}
