<?php

namespace App\Notifications;

use App\Models\SessionBooking;
use Illuminate\Notifications\Notification;

/** R-48 to R-50: database notices are saved in the booking transaction. */
class BookingUpdated extends Notification
{
    private const TITLES = [
        'booked' => 'Session booked', 'requested' => 'Booking requested',
        'approved' => 'Booking approved', 'rejected' => 'Booking rejected',
        'waitlisted' => 'Added to waiting list', 'promoted' => 'A place is yours',
        'cancelled' => 'Booking cancelled', 'payment' => 'Session payment recorded',
        'refund' => 'Refund recorded',
    ];

    public function __construct(private SessionBooking $booking, private string $type) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $session = $this->booking->session;
        $message = "{$session->name}, ".$session->starts_at->format('D j M Y, g:i A')." at {$this->booking->gym->name}.";
        if ($this->type === 'promoted' && $this->booking->status->value === 'requested') {
            $message .= ' Your place is reserved, awaiting staff approval.';
        }
        if ($this->type === 'rejected' && $this->booking->decision_reason) {
            $message .= ' Reason: '.$this->booking->decision_reason;
        }

        return [
            'title' => self::TITLES[$this->type], 'message' => $message, 'type' => $this->type,
            'gym_id' => $this->booking->gym_id, 'session_id' => $session->id,
            'booking_id' => $this->booking->id, 'status' => $this->booking->status->value,
        ];
    }
}
