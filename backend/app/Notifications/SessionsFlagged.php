<?php

namespace App\Notifications;

use Illuminate\Notifications\Notification;

/** R-46: one summary per leave approval, not one notice per occurrence. */
class SessionsFlagged extends Notification
{
    public function __construct(private int $gymId, private int $count, private string $coach) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->count === 1 ? 'Session needs attention' : 'Sessions need attention', 'type' => 'sessions_flagged',
            'message' => $this->count.($this->count === 1 ? ' session needs' : ' sessions need')." a new coach or time because {$this->coach} is on approved leave.",
            'gym_id' => $this->gymId, 'count' => $this->count,
        ];
    }
}
