<?php

use Illuminate\Support\Facades\Mail;

$event = \App\Models\Event::find(5);
$er = $event->registrations()->get();

$users = [];
foreach ($er as $reg) {
    if ($reg->responsible_user) {
        if ($reg->responsible_user) {
            $users[] = $reg->responsible_user;
        }
    } else {
        $users[] = $reg;
    }
}
$used_emails = collect([]);

$md_text = <<<'EOF'
# TOURNAMENT INFORMATION: PLEASE READ!

Tournament is quickly approaching! Please read and pay close attention to all of the following information to make your tournament day as successful and fun as possible.

Protocol:
- All competitors must be dressed in their clean, neat uniform with correctly tied belt and a set of 3 patches sewn on. Need patches? Ask your instructor TODAY! Not sure how to tie your belt? Ask a Black Belt! They will be happy to help you.
- All competitors must remove all watches and jewelry before entering the sparring ring for their competition.
- Students should also trim their finger and toenails short prior to competition day.
- All competitors will need to bring a filled out and signed waiver to participate in tournament. A copy of this waiver should be attached to this email.  A copy was also included with your initial registration. If you need another copy, please ask your instructor asap. We will also have blank copies at the venue if you forget yours at home.
- Be respectful to all students and instructors and show your mutual friendship throughout the day.
- Students are welcome to change into street clothes after their competition is over
- Students may leave when their division is done to eat, shop etc. until it is time for finals!  Or, stay through the end of preliminary competition to support their friends.

Finals/Demos:
- Students are strongly encouraged to return in the evening for finals matches and school demonstrations. This is usually students’ favorite part of the day!
- If you or your child advances to the finals round, you will be asked if you can return for finals competition in the evening. If you are unable to do so, please let your head table judges know at this time so we can complete your finals match during the morning. Please make every effort to make yourself available in the evening for finals and demos!

Parking/Venue:
- We have attached venue and parking information to this post as well.

If you have questions, please don’t hesitate to ask your instructor! We are all here to help you!

EOF;
$title = 'Winter 2024 Tournament Information';

foreach ($users as $user) {
    if ($used_emails->contains($user->email)) {
        continue;
    }
    echo $user->email."\n";
    $ids = collect($user->id);
    $ids = $ids->merge($user->family_members->pluck('id'));

    $regs = $event->registrations()->whereIn('user_id', $ids)->get();
    $registration_ids = $regs->pluck('pivot.id');

    $mail = new \App\Mail\GenericMessage();
    $mail->title = $title;
    $mail->body = $md_text;
    $mail->subject = $title;
    $mail->attach(resource_path('files/tournaments/winter-2024/Winter2024_Tournament_Packet.pdf'));
    Mail::to($user->email)
        ->send($mail);
    $used_emails->push($user->email);
}
echo 'OK';
