<?php

namespace App\Enums;

/** A customer is open until staff explicitly close the record as lost (R-69). */
enum CustomerStatus: string
{
    case Open = 'open';
    case Lost = 'lost';
}
