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
            // R-117: members who apply to coach here.
            'coach_applications' => ['view', 'approve'],
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
        // R-119: injury details need records.view; injuries.manage records and updates them.
        'health_records' => ['records' => ['view'], 'injuries' => ['manage']],
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
            // R-111: promos and vouchers. Staff make them, approvers (owner by default) turn them on.
            'offers' => ['view', 'manage', 'approve'],
        ],
        'inventory' => [
            'items' => ['view', 'manage'],
            'movements' => ['manage'],
            // R-120: safety checks on equipment and protective gear.
            'safety' => ['view', 'manage'],
        ],
        'procurement' => [
            'requests' => ['view', 'manage', 'approve'],
            'suppliers' => ['view', 'manage'],
            'orders' => ['view', 'manage'],
            'receipts' => ['manage'],
        ],
        'announcements' => ['general' => ['view', 'manage']],
        'decision_support' => ['general' => ['view']],
    ],
];
