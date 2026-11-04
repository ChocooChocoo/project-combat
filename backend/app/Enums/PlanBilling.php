<?php

namespace App\Enums;

/** Whether a membership plan is sold by time or by number of sessions (R-29). */
enum PlanBilling: string
{
    case Time = 'time';
    case Sessions = 'sessions';
}
