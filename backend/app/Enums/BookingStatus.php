<?php

namespace App\Enums;

/** R-49, R-50: requested bookings hold places; waitlisted bookings do not. */
enum BookingStatus: string
{
    case Requested = 'requested';
    case Booked = 'booked';
    case Waitlisted = 'waitlisted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function isLive(): bool
    {
        return in_array($this, [self::Requested, self::Booked, self::Waitlisted], true);
    }
}
