<?php

namespace App\Enums;

/** A follow-up moves from open to done or cancelled, never back (R-69). */
enum CustomerFollowUpStatus: string
{
    case Open = 'open';
    case Done = 'done';
    case Cancelled = 'cancelled';
}
