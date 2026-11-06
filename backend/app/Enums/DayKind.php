<?php

namespace App\Enums;

/** What a calendar day is for one employee, decided in one place (R-27, R-56, R-59). */
enum DayKind: string
{
    case Working = 'working';
    case Rest = 'rest';
    case HolidayRegular = 'holiday_regular';
    case HolidaySpecial = 'holiday_special';

    public function isHoliday(): bool
    {
        return $this === self::HolidayRegular || $this === self::HolidaySpecial;
    }
}
