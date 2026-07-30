<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class EventRegistered extends Mailable
{
    use Queueable, SerializesModels;

    public $event;

    public $registered_users;

    public $amount_paid;

    public $qr_image = null;

    public $tempfile;

    public $responsibleUser;

    /**
     * @var array|mixed
     */
    private mixed $attachments_to_send = null;

    /**
     * Create a new message instance. $responsibleUser is the account holder the
     * confirmation is sent to; used to build their household's meal voucher.
     *
     * @return void
     */
    public function __construct($event, $event_reg_records, $qr_image, $responsibleUser = null)
    {
        $event_reg_records = collect($event_reg_records);

        $this->event = $event;
        $this->registered_users = $event_reg_records;
        $this->responsibleUser = $responsibleUser;
        if ($qr_image) {
            $this->qr_image = $qr_image;
        }
        $er = $event->registrations()->whereIn('user_id', $event_reg_records->pluck('id'))->first();
        $this->amount_paid = $er->pivot->amount_paid;

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.events.registered')
            ->subject('Event Registered: '.$this->event->name);
    }

    public function attachments()
    {
        $attachments = [];

        if ($this->event->waiver_file_path) {
            $attachments[] = Attachment::fromPath(resource_path($this->event->waiver_file_path));
        }

        // Meal-ticket voucher for the household, when they bought meals.
        if ($this->responsibleUser) {
            try {
                $voucher = (new \App\Services\MealVoucherPdf())->forHousehold($this->event, $this->responsibleUser);
                if ($voucher) {
                    $attachments[] = Attachment::fromPath($voucher)
                        ->as(\Illuminate\Support\Str::slug($this->event->name).'-meal-ticket.pdf')
                        ->withMime('application/pdf');
                }
            } catch (\Throwable $e) {
                report($e); // never block the confirmation email on a voucher failure
            }
        }

        return $attachments;
    }
}
