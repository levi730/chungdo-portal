<?php

namespace App\Mail;

use App\Models\ProductOrder;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * Store order confirmation. Sent by ProductOrderFulfiller, and only by the call
 * that actually did the fulfillment, so a webhook racing the synchronous
 * response cannot send it twice.
 */
class ProductOrderPlaced extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public ProductOrder $order) {}

    public function build()
    {
        return $this->view('emails.store.order-placed')
            ->subject('Your order — Chung Do Association');
    }
}
