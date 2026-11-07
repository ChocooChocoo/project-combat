<?php

namespace App\Notifications;

use App\Models\Membership;
use Illuminate\Notifications\Notification;

class MembershipExpiryReminder extends Notification
{
    public function __construct(private Membership $membership, private int $daysLeft) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => 'Membership expires soon',
            'message' => $this->membership->plan->name.' ends on '.$this->membership->ends_on->format('j M Y')
                .' ('.$this->daysLeft.' '.($this->daysLeft === 1 ? 'day' : 'days').' left).',
            'gym_id' => $this->membership->gym_id, 'membership_id' => $this->membership->id,
            'ends_on' => $this->membership->ends_on->toDateString(), 'days_left' => $this->daysLeft,
            'url' => "/memberships/{$this->membership->id}",
        ];
    }
}
