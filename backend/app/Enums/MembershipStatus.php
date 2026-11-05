<?php

namespace App\Enums;

/** Where one membership stands, from application to its end (R-31, R-33, R-36). */
enum MembershipStatus: string
{
    case Applied = 'applied';
    case Rejected = 'rejected';
    case AwaitingPayment = 'awaiting_payment';
    case Active = 'active';
    case Frozen = 'frozen';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    /** Statuses that block a second application at the same gym. */
    public static function live(): array
    {
        return [self::Applied, self::AwaitingPayment, self::Active, self::Frozen];
    }
}
