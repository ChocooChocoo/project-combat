<?php

namespace App\Enums;

/** R-61: a payroll run is a draft, under review, or released. Released is terminal. */
enum PayrollStatus: string
{
    case Draft = 'draft';
    case InReview = 'in_review';
    case Released = 'released';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::InReview => 'In review',
            self::Released => 'Released',
        };
    }
}
