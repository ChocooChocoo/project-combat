<?php

namespace App\Enums;

/** How a gym pays an employee (Q-13). */
enum PayBasis: string
{
    case Monthly = 'monthly';
    case PerSession = 'per_session';
    case Both = 'both';
}
