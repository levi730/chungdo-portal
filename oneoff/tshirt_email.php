<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

$sql = 'SELECT er.*, u.firstname, u.lastname, u.email, u.responsible_user_id,
(SELECT er2.tshirt_size FROM event_registrations er2 WHERE er2.event_id = 1 AND er2.user_id = er.user_id) as last_reg
FROM event_registrations er
INNER JOIN users u ON u.id = er.user_id
WHERE er.event_id = 2 ';

$res = DB::select(DB::raw($sql));

foreach ($res as $rec) {
    $mailable = new \App\Mail\GenericMessage();
    $mailable->subject = 'Oops! Tournament registration t-shirt mishap';
    $mailable->signoff = false;
    $mailable->replyTo('mike@chungdo.org');
    $mailable->title = "Hello, {$rec->firstname}!";
    $mailable->body = "You are receiving this message because you registered for the 2022 Summer Tournament on chungdo.com.

I just recently realized that I set up the registration form for the tournament, but didn't ask for t-shirt size.  😳

";
    if ($rec->last_reg) {
        $mailable->body .= "When you registered for the last tournament, you selected '{$rec->last_reg}' as your t-shirt size, so I used that for this year's tournament as well.

If this is CORRECT, you don't have to do anything.  You'll get the t-shirt in the same size as last year.

If this is INCORRECT, please reply to this message and let me know the t-shirt size you would like.";
    } else {
        $mailable->body .= "I couldn't find a t-shirt size selection for last year's tournament.  Please reply to this message and let me know the t-shirt size you would like.";
    }

    $mailable->body .= '
Thank you!

Mike L.';

    Mail::to($rec->email)
        ->send($mailable);

    echo 'Mail sent to '.$rec->firstname.' '.$rec->lastname.".\n";
}
echo "Done.\n\n";
