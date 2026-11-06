<?php

namespace App\Enums;

/** A purchase request waits for a different permitted approver (R-76). */
enum PurchaseRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
