<?php

namespace App\Enums;

/** R-60: the statutory tables a payroll run can use. */
enum StatutoryKind: string
{
    case Sss = 'sss';
    case Philhealth = 'philhealth';
    case Pagibig = 'pagibig';
    case TaxSemiMonthly = 'tax_semi_monthly';
    case TaxMonthly = 'tax_monthly';

    public function label(): string
    {
        return match ($this) {
            self::Sss => 'SSS',
            self::Philhealth => 'PhilHealth',
            self::Pagibig => 'Pag-IBIG',
            self::TaxSemiMonthly => 'Withholding tax (semi-monthly)',
            self::TaxMonthly => 'Withholding tax (monthly)',
        };
    }

    public function isTax(): bool
    {
        return $this === self::TaxSemiMonthly || $this === self::TaxMonthly;
    }

    public static function taxFor(string $frequency): self
    {
        return $frequency === 'monthly' ? self::TaxMonthly : self::TaxSemiMonthly;
    }

    /** The three contribution kinds, in payslip order. */
    public static function contributions(): array
    {
        return [self::Sss, self::Philhealth, self::Pagibig];
    }
}
