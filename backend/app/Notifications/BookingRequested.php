<?php

namespace App\Notifications;

use App\Models\SessionBooking;
use Illuminate\Notifications\Notification;

/** R-49: only booking approvers receive this notice. */
class BookingRequested extends Notification
{
    public function __construct(private SessionBooking $booking) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $session = $this->booking->session;
        $user = $this->booking->user;

        return [
            'title' => 'Booking needs approval',
            'message' => "{$user->first_name} {$user->last_name} requested {$session->name}, ".
                $session->starts_at->format('D j M Y, g:i A')." at {$this->booking->gym->name}.",
            'gym_id' => $this->booking->gym_id, 'session_id' => $session->id,
            'booking_id' => $this->booking->id, 'status' => $this->booking->status->value,
        ];
    }
}
