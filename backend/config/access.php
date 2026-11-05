<?php

// Starting templates for owner-made roles (R-08). Owners copy one and change it freely.
return [
    'templates' => [
        'coach' => [
            'training' => 'manage',
            'members' => 'view',
        ],
        'front_desk' => [
            'members' => 'approve',
            'training' => 'manage',
            'finance' => 'manage',
            'customers' => 'manage',
            'announcements' => 'manage',
        ],
        'finance' => [
            'finance' => 'approve',
            'payroll' => 'approve',
            'procurement' => 'approve',
            'decision_support' => 'view',
        ],
        'hr' => [
            'hr' => 'manage',
            'payroll' => 'manage',
        ],
    ],
];
