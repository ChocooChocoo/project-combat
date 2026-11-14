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
            // Exact list: everything in HR, including hr.overtime.approve. R-117: coach applications
            // are seen by HR but approved by the owner unless the owner grants approve on purpose.
            'hr' => [
                'hr.staff.view', 'hr.staff.manage', 'hr.roles.view', 'hr.roles.manage',
                'hr.schedules.view', 'hr.schedules.manage', 'hr.leave.view', 'hr.leave.manage',
                'hr.holidays.manage', 'hr.attendance.view', 'hr.attendance.manage', 'hr.overtime.approve',
                'hr.performance.view', 'hr.performance.manage', 'hr.coach_applications.view',
                'hr.job_postings.view', 'hr.job_postings.manage', 'hr.job_applications.view',
            ],
            'payroll' => 'manage',
        ],
    ],
];
