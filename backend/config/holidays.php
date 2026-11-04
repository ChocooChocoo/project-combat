<?php

// Philippine national holidays (R-27). Fixed dates repeat every year.
// Movable dates follow the yearly proclamations, so they are listed per year.
// Islamic holidays depend on the moon sighting and may move by a day.
return [
    'fixed' => [
        ['month' => 1, 'day' => 1, 'name' => "New Year's Day"],
        ['month' => 2, 'day' => 25, 'name' => 'EDSA People Power Anniversary'],
        ['month' => 4, 'day' => 9, 'name' => 'Araw ng Kagitingan'],
        ['month' => 5, 'day' => 1, 'name' => 'Labor Day'],
        ['month' => 6, 'day' => 12, 'name' => 'Independence Day'],
        ['month' => 8, 'day' => 21, 'name' => 'Ninoy Aquino Day'],
        ['month' => 11, 'day' => 1, 'name' => "All Saints' Day"],
        ['month' => 11, 'day' => 30, 'name' => 'Bonifacio Day'],
        ['month' => 12, 'day' => 8, 'name' => 'Immaculate Conception'],
        ['month' => 12, 'day' => 25, 'name' => 'Christmas Day'],
        ['month' => 12, 'day' => 30, 'name' => 'Rizal Day'],
        ['month' => 12, 'day' => 31, 'name' => 'Last Day of the Year'],
    ],

    'years' => [
        2026 => [
            ['date' => '2026-02-17', 'name' => 'Chinese New Year'],
            ['date' => '2026-03-20', 'name' => "Eid'l Fitr"],
            ['date' => '2026-04-02', 'name' => 'Maundy Thursday'],
            ['date' => '2026-04-03', 'name' => 'Good Friday'],
            ['date' => '2026-04-04', 'name' => 'Black Saturday'],
            ['date' => '2026-05-27', 'name' => "Eid'l Adha"],
            ['date' => '2026-08-31', 'name' => 'National Heroes Day'],
        ],
        2027 => [
            ['date' => '2027-02-06', 'name' => 'Chinese New Year'],
            ['date' => '2027-03-10', 'name' => "Eid'l Fitr"],
            ['date' => '2027-03-25', 'name' => 'Maundy Thursday'],
            ['date' => '2027-03-26', 'name' => 'Good Friday'],
            ['date' => '2027-03-27', 'name' => 'Black Saturday'],
            ['date' => '2027-05-17', 'name' => "Eid'l Adha"],
            ['date' => '2027-08-30', 'name' => 'National Heroes Day'],
        ],
    ],
];
