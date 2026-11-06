<?php

// The exact permissions code may check, written "module.resource.action".
// Owners hold every active permission. Employee roles hold only the strings
// chosen for them. A check for a string that is not listed here fails loudly.
return [
    'catalog' => [
        'hr' => [
            'staff' => ['view', 'manage'],
            'roles' => ['view', 'manage'],
            'schedules' => ['view', 'manage'],
            'leave' => ['view', 'manage'],
            'holidays' => ['manage'],
            'attendance' => ['view', 'manage'],
            'overtime' => ['approve'],
            'performance' => ['view', 'manage'],
        ],
        'gym_setup' => [
            'settings' => ['view', 'manage'],
            'rooms' => ['view', 'manage'],
            'services' => ['view', 'manage'],
        ],
        'training' => [
            'sessions' => ['view', 'manage'],
            'bookings' => ['view', 'manage', 'approve'],
            'attendance' => ['view', 'manage'],
            'check_ins' => ['manage'],
            'athletes' => ['view', 'manage'],
        ],
        'members' => [
            'plans' => ['view', 'manage'],
            'memberships' => ['view', 'manage', 'approve'],
        ],
        'health_records' => ['records' => ['view']],
        'finance' => [
            'payments' => ['view', 'manage'],
            'expenses' => ['view', 'manage'],
            'receivables' => ['view'],
            'payables' => ['view', 'manage'],
            'budgets' => ['view', 'manage'],
            'reports' => ['view'],
        ],
        'payroll' => [
            'runs' => ['view', 'manage', 'approve'],
            'rates' => ['manage'],
        ],
        'customers' => [
            'records' => ['view', 'manage'],
            'groups' => ['manage'],
        ],
        'inventory' => [
            'items' => ['view', 'manage'],
            'movements' => ['manage'],
        ],
        'procurement' => [
            'requests' => ['view', 'manage', 'approve'],
            'suppliers' => ['view', 'manage'],
            'orders' => ['view', 'manage'],
            'receipts' => ['manage'],
        ],
        'announcements' => ['general' => ['view', 'manage', 'approve']],
        'decision_support' => ['general' => ['view', 'manage', 'approve']],
    ],
];
