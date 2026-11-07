<?php

namespace App\Notifications;

use App\Models\SessionBooking;
use Illuminate\Notifications\Notification;

class SessionReminder extends Notification
{
    public function __construct(private SessionBooking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $session = $this->booking->session;

        return [
            'title' => 'Session reminder',
            'message' => $session->name.' starts '.$session->starts_at->format('j M Y, g:i A')." in {$session->room->name}.",
            'gym_id' => $this->booking->gym_id, 'session_id' => $session->id, 'booking_id' => $this->booking->id,
            'starts_at' => $session->starts_at->toIso8601String(),
            'url' => "/gyms/{$this->booking->gym_id}/sessions/{$session->id}",
        ];
    }
}
