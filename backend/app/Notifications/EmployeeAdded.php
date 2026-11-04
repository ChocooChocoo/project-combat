<?php

namespace App\Notifications;

use App\Models\Gym;
use Illuminate\Notifications\Notification;

/** Tells an existing account it has been added to a gym (R-22). */
class EmployeeAdded extends Notification
{
    public function __construct(private Gym $gym) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => "You were added to {$this->gym->name}",
            'message' => "You are now an employee at {$this->gym->name}. Open the workspace to see your work.",
            'gym_id' => $this->gym->id,
        ];
    }
}
