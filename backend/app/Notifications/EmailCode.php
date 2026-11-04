<?php

namespace App\Notifications;

use App\Support\OneTimeCodes;
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
        $subject = match ($this->purpose) {
            OneTimeCodes::RESET => 'Reset your password',
            OneTimeCodes::INVITE => 'Set up your gym account',
            default => 'Confirm your email',
        };

        return (new MailMessage)
            ->subject($subject)
            ->line("Your code is {$this->code}.")
            ->line('It expires in '.config('auth.code_minutes').' minutes. If you did not ask for it, ignore this email.');
    }
}
