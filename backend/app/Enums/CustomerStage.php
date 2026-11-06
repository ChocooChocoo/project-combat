<?php

namespace App\Enums;

/** The derived CRM stage. It is never stored on the customer row (R-69, R-71). */
enum CustomerStage: string
{
    case Lead = 'lead';
    case Applied = 'applied';
    case Member = 'member';
    case Lost = 'lost';
}
