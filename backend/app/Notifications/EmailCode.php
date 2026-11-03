<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Emails a one-time code. Queued so the person never waits for the email to send. */
class EmailCode extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public string $code, public string $purpose) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $subject = $this->purpose === 'reset' ? 'Reset your password' : 'Confirm your email';

        return (new MailMessage)
            ->subject($subject)
            ->line("Your code is {$this->code}.")
            ->line('It expires in '.config('auth.code_minutes').' minutes. If you did not ask for it, ignore this email.');
    }
}
