<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Practice;

class SubscriptionCancelledNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $practice;

    public function __construct(Practice $practice)
    {
        $this->practice = $practice;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Subscription Cancelled')
            ->greeting('Hello ' . $notifiable->name)
            ->line("Your subscription for {$this->practice->name} has been cancelled.")
            ->line('If this was not intentional, please contact support.')
            ->salutation('Regards, MyOsteopathy Team');
    }
}
