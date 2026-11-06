<?php

namespace App\Support\Payroll;

use App\Enums\StatutoryKind;
use RuntimeException;

/** R-60: a required statutory table is missing for the period. */
class MissingStatutoryRate extends RuntimeException
{
    public function __construct(public readonly StatutoryKind $kind)
    {
        parent::__construct("No {$kind->label()} rate is effective on or before the period end.");
    }
}
