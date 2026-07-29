<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AccountLinkRequested extends Notification
{
    use Queueable;

    public function __construct(public User $requester) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->requester->full_name.' wants to link accounts with you')
            ->greeting('Hi '.$notifiable->firstname.',')
            ->line($this->requester->full_name.' has asked to link their family account with yours on '.config('app.name').'.')
            ->line('If you accept, the two of you will be able to register and pay for each other and for each other\'s students. Nothing about your existing account changes, and you can undo this later.')
            ->action('Review the request', route('profile.edit').'#tabs-student')
            ->line('If you weren\'t expecting this, you can safely ignore this email or decline the request from your profile.');
    }
}
