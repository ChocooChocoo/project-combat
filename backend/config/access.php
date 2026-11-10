<?php

// Starting templates for owner-made roles (R-08). Owners copy one and change it freely.
return [
    'names' => ['coach' => 'Coach', 'front_desk' => 'Front desk', 'finance' => 'Finance', 'hr' => 'HR'],
    'templates' => [
        'coach' => [
            'training' => 'manage',
            'members' => 'view',
        ],
        'front_desk' => [
            'members' => 'approve',
            'training' => 'manage',
            // Exact list: the front desk runs the payments desk, not budgets or payables.
            'finance' => ['finance.payments.view', 'finance.payments.manage', 'finance.receivables.view'],
            'customers' => 'manage',
            'inventory' => ['inventory.items.view', 'inventory.movements.manage'],
            'announcements' => 'manage',
        ],
        'finance' => [
            'finance' => 'approve',
            'payroll' => 'approve',
            'procurement' => 'approve',
            'inventory' => ['inventory.items.view'],
            'decision_support' => 'view',
        ],
        'hr' => [
            // Approve adds hr.overtime.approve to the HR starter set.
            'hr' => 'approve',
            'payroll' => 'manage',
        ],
    ],
];
