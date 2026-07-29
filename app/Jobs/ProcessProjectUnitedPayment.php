<?php
namespace App\Jobs;

use Log;
use App\Mail\ProjectUnitedReceipt;
use App\Models\ProjectUnitedTransaction;
use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Mail;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Contracts\Queue\ShouldQueue;

class ProcessProjectUnitedPayment implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public $session;

    public function __construct($session)
    {
        $this->session = $session;
    }

    public function handle()
    {
        Log::info('Processing Project United Payment: '.$this->session->id);
        // Avoid duplicates
        if (ProjectUnitedTransaction::where('stripe_id', $this->session->id)->exists()) {
            return;
        }

        $md = $this->session->metadata->toArray();

        if(array_key_exists("raw_items", $md)) {
            $md['raw_items'] = json_decode($md['raw_items']);
        }

        $trans = ProjectUnitedTransaction::create([
            'trans_type' => $this->session->metadata->trans_type,
            'user_id' => $this->session->metadata->user_id,
            'stripe_id' => $this->session->id,
            'email' => $this->session->customer_email,
            'amount' => $this->session->amount_total / 100,
            'metadata' => $md,
        ]);

        Log::info('Sending Email - Project United Payment: '.$this->session->id);
        $resp = Mail::to($trans->email)->send(new ProjectUnitedReceipt($trans));
        Log::info('Result - Project United Payment: '.$this->session->id . print_r($resp, true));


    }

}
