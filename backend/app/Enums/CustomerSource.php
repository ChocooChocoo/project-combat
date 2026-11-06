<?php

namespace App\Enums;

/** How a prospect first reached the gym (R-69). */
enum CustomerSource: string
{
    case WalkIn = 'walk_in';
    case Referral = 'referral';
    case Social = 'social';
    case Website = 'website';
    case Event = 'event';
    case Other = 'other';
}
