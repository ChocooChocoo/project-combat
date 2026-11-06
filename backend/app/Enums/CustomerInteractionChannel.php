<?php

namespace App\Enums;

/** How an interaction happened (R-70). */
enum CustomerInteractionChannel: string
{
    case Phone = 'phone';
    case Sms = 'sms';
    case Email = 'email';
    case Messenger = 'messenger';
    case WalkIn = 'walk_in';
    case Other = 'other';
}
