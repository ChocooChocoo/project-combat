<?php

// The gym's offer: services (R-28) and membership plans (R-29).
return [
    'activity_types' => [
        'classes' => 'Classes',
        'programs' => 'Programs',
        'private_coaching' => 'Private coaching',
        'sparring' => 'Sparring',
        'open_mat' => 'Open mat',
        'group_session' => 'Group session',
    ],

    'levels' => [
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced' => 'Advanced',
        'all' => 'All levels',
    ],

    // D-35: an athlete's level, lowest first. Booking needs at least the session's level.
    'athlete_levels' => [
        'beginner' => 'Beginner',
        'intermediate' => 'Intermediate',
        'advanced' => 'Advanced',
    ],

    'frequencies' => [
        'daily' => 'Daily',
        'weekly' => 'Weekly',
        'monthly' => 'Monthly',
        'one_off' => 'One off',
    ],

    'service_photos' => [
        'max' => 5,
        'max_kb' => 4096,
    ],

    'plan_billing' => [
        'time' => 'By time',
        'sessions' => 'By sessions',
    ],
];
