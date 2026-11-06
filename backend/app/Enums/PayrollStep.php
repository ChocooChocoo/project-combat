<?php

namespace App\Enums;

/** R-61: the approval chain. The owner is always the last step. */
enum PayrollStep: string
{
    case HrReview = 'hr_review';
    case FinanceReview = 'finance_review';
    case Owner = 'owner';

    public function label(): string
    {
        return config("payroll.steps.{$this->value}", ucfirst(str_replace('_', ' ', $this->value)));
    }

    public function isOwner(): bool
    {
        return $this === self::Owner;
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_map(fn (self $step) => $step->value, self::cases());
    }
}
