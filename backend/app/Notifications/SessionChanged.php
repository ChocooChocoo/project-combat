<?php

namespace App\Notifications;

use App\Models\TrainingSession;
use Illuminate\Notifications\Notification;

/** R-45: database notices share the session transaction. */
class SessionChanged extends Notification
{
    public function __construct(private TrainingSession $session, private string $type, private string $message) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => match ($this->type) {
                'cancelled' => 'Session cancelled', 'coach_changed' => 'Session coach changed',
                'flagged' => 'Session may change', 'flag_cleared' => 'Session confirmed', default => 'Session moved',
            },
            'type' => $this->type, 'message' => $this->session->name.': '.$this->message,
            'gym_id' => $this->session->gym_id, 'session_id' => $this->session->id,
        ];
    }
}
