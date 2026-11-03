<?php

namespace App\Enums;

/** How a person relates to a gym (R-07). Owner-made job roles are App\Models\GymRole (R-08). */
enum GymPersonType: string
{
    case Owner = 'owner';
    case Employee = 'employee';
    case Member = 'member';
}
