<?php

namespace App\Enums;

/** How a staff attendance day ended up (R-56). */
enum AttendanceStatus: string
{
    case Present = 'present';
    case Late = 'late';
    case Absent = 'absent';
}
