<?php

namespace App\Enums;

/** The life of a hand-entered activity supply need (R-75, R-76). */
enum SupplyNeedStatus: string
{
    case Open = 'open';
    case Requested = 'requested';
    case Closed = 'closed';
}
