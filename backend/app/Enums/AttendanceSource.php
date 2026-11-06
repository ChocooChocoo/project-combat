<?php

namespace App\Enums;

/** Where a staff attendance row came from (R-56). */
enum AttendanceSource: string
{
    case Self = 'self';
    case Desk = 'desk';
    case System = 'system';
}
