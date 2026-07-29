<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class GenericMessage extends Mailable
{
    use Queueable, SerializesModels;

    public $title;

    public $subject;

    public $body;

    //public $attachments;
    public $button_url;

    public $button_text;

    public $signoff = 'Thank you!';

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
        return $this->markdown('emails.generic_message');
    }
}
