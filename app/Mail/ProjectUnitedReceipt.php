<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class ProjectUnitedReceipt extends Mailable
{
    use Queueable, SerializesModels;

    public $trans;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($trans)
    {

        $this->trans = $trans;

    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        switch($this->trans->trans_type) {
            case "project_united_donation":
                $subject = 'Project United: Donation Receipt';
                $view = 'emails.project-united.donation_receipt';
                break;
            case "project_united_tshirt":
                $subject = 'Project United: T-Shirt Order Receipt';
                $view = 'emails.project-united.tshirt_receipt';
                break;
            case "project_united_2026_tshirt":
                $subject = 'Project United: 20026 T-Shirt Order Receipt';
                $view = 'emails.project-united.tshirt_receipt';
                break;
            case "project_united_hoodie":
                $subject = 'Project United: Hoodie Order Receipt';
                $view = 'emails.project-united.hoodie_receipt';
                break;
            case "project_united_2026_hoodie":
                $subject = 'Project United: 2026 Hoodie Order Receipt';
                $view = 'emails.project-united.hoodie_receipt';
                break;
            default:
                throw new \Exception('Invalid transaction type');
        }

        return $this->view($view, ['trans'=>$this->trans])
            ->subject($subject);
    }

}
