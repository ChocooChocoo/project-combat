<?php

// How gym recommendations are ranked (R-98). The weights add up to 100.
return [
    'weights' => [
        'discipline' => 40,
        'price' => 30,
        'distance' => 30,
    ],

    'distance_cap_km' => 50,

    'limit' => 10,

    'preferred_times' => [
        'morning' => 'Morning',
        'afternoon' => 'Afternoon',
        'evening' => 'Evening',
    ],
];
