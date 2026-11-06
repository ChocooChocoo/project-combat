<?php

namespace App\Enums;

/** R-59: employees get statutory deductions; contractors do not. */
enum EmploymentType: string
{
    case Employee = 'employee';
    case Contractor = 'contractor';

    public function label(): string
    {
        return $this === self::Employee ? 'Employee' : 'Contractor';
    }
}
