<?php

namespace App\Notifications\Admin;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use App\Models\Practice;

class StripePaymentFailedNotification extends Notification implements ShouldQueue
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
            ->subject('🚨 Payment Failed for Practice')
            ->greeting('Heads up!')
            ->line("A payment for practice **{$this->practice->name}** (ID: {$this->practice->id}) has failed.")
            ->line('Please check the billing info or notify the practice owner.')
            ->salutation('— MyOsteopathy System');
    }
}
