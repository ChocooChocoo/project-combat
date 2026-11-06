<?php

namespace App\Enums;

/** R-57: only approved overtime is paid. */
enum OvertimeStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
}
