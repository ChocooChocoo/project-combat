<?php

namespace App\Enums;

/** Where one leave request stands (R-25). */
enum LeaveRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';
}
