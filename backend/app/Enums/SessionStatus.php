<?php

namespace App\Enums;

/** R-42: cancelled sessions stay in the schedule's history. */
enum SessionStatus: string
{
    case Scheduled = 'scheduled';
    case Cancelled = 'cancelled';
}
