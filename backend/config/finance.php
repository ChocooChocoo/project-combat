<?php

// Money categories (R-63, R-64). A purchase is an expense when it is received;
// a supplier payment is cash out that settles it, never a second expense.
return [
    'income_types' => [
        'membership' => 'Membership',
        'session' => 'Session',
        'private_coaching' => 'Private coaching',
        'walk_in' => 'Walk-in',
        'merchandise' => 'Merchandise',
        'other' => 'Other income',
    ],

    'expense_categories' => [
        'rent' => 'Rent',
        'utilities' => 'Utilities',
        'equipment' => 'Equipment',
        'supplies' => 'Supplies',
        'maintenance' => 'Maintenance',
        'marketing' => 'Marketing',
        'professional_fees' => 'Professional fees',
        'taxes_licenses' => 'Taxes and licences',
        'payroll' => 'Payroll',
        'statutory_contributions' => 'Statutory contributions',
        'purchases' => 'Purchases',
        'other' => 'Other expense',
    ],

    'purchase_expense_timing' => 'receipt',
];
