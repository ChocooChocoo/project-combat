<?php

namespace App\Enums;

/** The fixed kind of account chosen at sign-up (R-01, R-07). It cannot be changed later. */
enum AccountType: string
{
    case Member = 'member';
    case Owner = 'owner';
}
