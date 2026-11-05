<?php

$later = ['general' => ['view', 'manage', 'approve']];

return [
    'catalog' => [
        'hr' => [
            'staff' => ['view', 'manage'],
            'roles' => ['view', 'manage'],
            'schedules' => ['view', 'manage'],
            'leave' => ['view', 'manage'],
            'holidays' => ['manage'],
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
        'finance' => ['payments' => ['view', 'manage']],
        'payroll' => $later,
        'customers' => $later,
        'inventory' => $later,
        'procurement' => $later,
        'announcements' => $later,
        'decision_support' => $later,
    ],
];
