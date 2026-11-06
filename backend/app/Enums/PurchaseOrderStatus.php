<?php

namespace App\Enums;

/** The four states a purchase order can show (R-79). */
enum PurchaseOrderStatus: string
{
    case Pending = 'pending';
    case PartlyReceived = 'partly_received';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function open(): bool
    {
        return in_array($this, [self::Pending, self::PartlyReceived], true);
    }
}
