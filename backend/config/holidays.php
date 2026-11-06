<?php

// Philippine national holidays (R-27). Fixed dates repeat every year.
// Movable dates follow the yearly proclamations, so they are listed per year.
// "regular" and "special" decide the worked-holiday premium (R-59).
// Verify against the current proclamation before a payroll run; this list is
// a starting point, not legal advice.
return [
    'fixed' => [
        ['month' => 1, 'day' => 1, 'name' => "New Year's Day", 'kind' => 'regular'],
        ['month' => 2, 'day' => 25, 'name' => 'EDSA People Power Anniversary', 'kind' => 'special'],
        ['month' => 4, 'day' => 9, 'name' => 'Araw ng Kagitingan', 'kind' => 'regular'],
        ['month' => 5, 'day' => 1, 'name' => 'Labor Day', 'kind' => 'regular'],
        ['month' => 6, 'day' => 12, 'name' => 'Independence Day', 'kind' => 'regular'],
        ['month' => 8, 'day' => 21, 'name' => 'Ninoy Aquino Day', 'kind' => 'special'],
        ['month' => 11, 'day' => 1, 'name' => "All Saints' Day", 'kind' => 'special'],
        ['month' => 11, 'day' => 30, 'name' => 'Bonifacio Day', 'kind' => 'regular'],
        ['month' => 12, 'day' => 8, 'name' => 'Immaculate Conception', 'kind' => 'special'],
        ['month' => 12, 'day' => 25, 'name' => 'Christmas Day', 'kind' => 'regular'],
        ['month' => 12, 'day' => 30, 'name' => 'Rizal Day', 'kind' => 'regular'],
        ['month' => 12, 'day' => 31, 'name' => 'Last Day of the Year', 'kind' => 'special'],
    ],

    'years' => [
        2026 => [
            ['date' => '2026-02-17', 'name' => 'Chinese New Year', 'kind' => 'special'],
            ['date' => '2026-03-20', 'name' => "Eid'l Fitr", 'kind' => 'regular'],
            ['date' => '2026-04-02', 'name' => 'Maundy Thursday', 'kind' => 'regular'],
            ['date' => '2026-04-03', 'name' => 'Good Friday', 'kind' => 'regular'],
            ['date' => '2026-04-04', 'name' => 'Black Saturday', 'kind' => 'special'],
            ['date' => '2026-05-27', 'name' => "Eid'l Adha", 'kind' => 'regular'],
            ['date' => '2026-08-31', 'name' => 'National Heroes Day', 'kind' => 'regular'],
        ],
        2027 => [
            ['date' => '2027-02-06', 'name' => 'Chinese New Year', 'kind' => 'special'],
            ['date' => '2027-03-10', 'name' => "Eid'l Fitr", 'kind' => 'regular'],
            ['date' => '2027-03-25', 'name' => 'Maundy Thursday', 'kind' => 'regular'],
            ['date' => '2027-03-26', 'name' => 'Good Friday', 'kind' => 'regular'],
            ['date' => '2027-03-27', 'name' => 'Black Saturday', 'kind' => 'special'],
            ['date' => '2027-05-17', 'name' => "Eid'l Adha", 'kind' => 'regular'],
            ['date' => '2027-08-30', 'name' => 'National Heroes Day', 'kind' => 'regular'],
        ],
    ],
];
