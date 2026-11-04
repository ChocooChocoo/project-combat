<?php

// HR reference lists (R-24 to R-27). The owner may change working days in the workspace settings (Q-21).
return [
    'weekdays' => [
        'mon' => 'Monday',
        'tue' => 'Tuesday',
        'wed' => 'Wednesday',
        'thu' => 'Thursday',
        'fri' => 'Friday',
        'sat' => 'Saturday',
        'sun' => 'Sunday',
    ],

    // Q-19: the old Philippine leave list. Days are the yearly allowance.
    'leave_types' => [
        'vacation' => ['label' => 'Vacation Leave', 'days' => 15, 'paid' => true],
        'sick' => ['label' => 'Sick Leave', 'days' => 15, 'paid' => true],
        'service_incentive' => ['label' => 'Service Incentive Leave', 'days' => 5, 'paid' => true],
        'solo_parent' => ['label' => 'Solo Parent Leave', 'days' => 7, 'paid' => true],
        'bereavement' => ['label' => 'Bereavement Leave', 'days' => 3, 'paid' => true],
        'emergency' => ['label' => 'Emergency Leave', 'days' => 3, 'paid' => true],
        'maternity' => ['label' => 'Maternity Leave', 'days' => 105, 'paid' => true],
        'paternity' => ['label' => 'Paternity Leave', 'days' => 7, 'paid' => true],
        'special_women' => ['label' => 'Special Leave for Women', 'days' => 60, 'paid' => true],
        'vawc' => ['label' => 'VAWC Leave', 'days' => 10, 'paid' => true],
    ],

    'pay_bases' => [
        'monthly' => 'Monthly',
        'per_session' => 'Per session',
        'both' => 'Monthly and per session',
    ],

    'attachment' => [
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
        'max_kb' => 5120,
    ],
];
