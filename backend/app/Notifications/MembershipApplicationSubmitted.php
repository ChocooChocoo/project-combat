<?php

namespace App\Notifications;

use App\Models\Membership;
use Illuminate\Notifications\Notification;

/** Tells each person who may decide that a membership application is waiting (R-31, R-33). */
class MembershipApplicationSubmitted extends Notification
{
    public function __construct(private Membership $membership) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $name = trim("{$this->membership->user->first_name} {$this->membership->user->last_name}");

        return [
            'title' => 'New membership application',
            'message' => "{$name} applied for {$this->membership->plan->name}.",
            'gym_id' => $this->membership->gym_id,
            'membership_id' => $this->membership->id,
            'status' => $this->membership->status->value,
        ];
    }
}
