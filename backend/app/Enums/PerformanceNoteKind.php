<?php

namespace App\Enums;

/** R-58: the kinds of performance note a manager may write. */
enum PerformanceNoteKind: string
{
    case Commendation = 'commendation';
    case Concern = 'concern';
    case Review = 'review';
    case General = 'general';
}
