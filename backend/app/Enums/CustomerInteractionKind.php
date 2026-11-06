<?php

namespace App\Enums;

/** What one CRM history entry records (R-70). */
enum CustomerInteractionKind: string
{
    case Interaction = 'interaction';
    case Feedback = 'feedback';
    case Concern = 'concern';
    case Preference = 'preference';
}
