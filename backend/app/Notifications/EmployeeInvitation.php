<?php

namespace App\Notifications;

use App\Models\Gym;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Invites a new employee to set their own password (R-06, R-22). Queued like EmailCode. */
class EmployeeInvitation extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Gym $gym, public string $code, public string $email) {}

    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Set up your account for {$this->gym->name}")
            ->line("You have been added to {$this->gym->name}.")
            ->line("Your code is {$this->code}.")
            ->action('Set up your account', $this->url())
            ->line('It expires in '.config('auth.code_minutes').' minutes. If you were not expecting this, ignore this email.');
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "Set up your account for {$this->gym->name}",
            'message' => "Use code {$this->code} to set your password.",
            'gym_id' => $this->gym->id,
            'code' => $this->code,
            'url' => $this->url(),
        ];
    }

    private function url(): string
    {
        return rtrim(config('app.frontend_url'), '/').'/accept-invite?email='.urlencode($this->email);
    }
}
