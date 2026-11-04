<?php

namespace App\Notifications;

use App\Models\Gym;
use Illuminate\Notifications\Notification;

/** R-17: tells the applicant the verification decision (spec page 18). */
class GymApplicationDecided extends Notification
{
    public function __construct(private Gym $gym) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $name = $this->gym->name;

        [$title, $message] = match ($this->gym->status->value) {
            'approved' => ["{$name} is approved", 'Your gym is now live on the platform with its '.config("verification.tiers.{$this->gym->approved_tier}.badge").' badge.'],
            'rejected' => ["{$name} was not approved", 'Reason: '.$this->gym->decision_reason],
            'returned' => ["{$name} needs corrections", 'Fix these and send it again: '.implode('; ', $this->gym->return_items ?? [])],
        };

        return ['title' => $title, 'message' => $message, 'gym_id' => $this->gym->id, 'status' => $this->gym->status->value];
    }
}
