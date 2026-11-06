<?php

namespace App\Enums;

/** R-57: why extra time was worked. */
enum OvertimeKind: string
{
    case AfterShift = 'after_shift';
    case RestDay = 'rest_day';
    case Manual = 'manual';
}
