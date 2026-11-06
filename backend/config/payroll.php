<?php

// Payroll and attendance rules (R-56, R-59, R-61). Rates live in the
// statutory_rates table, not here: the law changes and old periods keep theirs.
return [
    'frequencies' => [
        'semi_monthly' => 'Twice a month',
        'monthly' => 'Once a month',
    ],

    'steps' => [
        'hr_review' => 'HR review',
        'finance_review' => 'Finance review',
        'owner' => 'Owner',
    ],

    'overtime_multiplier' => 1.25,

    // Premium paid on top of the daily rate when the holiday is worked.
    'holiday_premiums' => [
        'regular' => 1.00,
        'special' => 0.30,
    ],

    'default_grace_minutes' => 10,
    'default_overtime_min_minutes' => 30,
];
