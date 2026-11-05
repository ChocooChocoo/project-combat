<?php

namespace App\Enums;

/** The kinds of entry in an athlete's record (R-37 to R-39). */
enum AthleteEntryType: string
{
    case Promotion = 'promotion';
    case Assessment = 'assessment';
    case Competition = 'competition';
    case Achievement = 'achievement';
}
