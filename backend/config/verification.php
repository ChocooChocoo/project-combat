<?php

// Gym owner verification rules (spec pages 10 and 11). The screens read these
// through GET /api/gym-applications/requirements, so the lists live only here.

$tier1 = [
    // DTI for a sole owner, SEC for a company or partnership: one of the two (C-05).
    ['types' => ['dti', 'sec'], 'min' => 1],
    ['types' => ['mayors_permit'], 'min' => 1],
    ['types' => ['barangay_clearance'], 'min' => 1],
];

return [
    // "max" is how many files of that type one application may hold (default 1).
    // "expiry" (required|optional) asks for an expiry date; "match" lists the names the browser reads for (owner, gym) (R-133).
    'documents' => [
        'dti' => ['label' => 'DTI certificate', 'proves' => 'The business name is registered to a sole owner.', 'who' => 'Sole owner', 'expiry' => 'required', 'match' => ['owner', 'gym']],
        'sec' => ['label' => 'SEC certificate', 'proves' => 'The company or partnership is registered.', 'who' => 'Company or partnership', 'match' => ['gym']],
        'mayors_permit' => ['label' => "Mayor's or business permit", 'proves' => 'The gym may operate in its city this year.', 'who' => 'Every applicant', 'expiry' => 'required', 'match' => ['gym']],
        'barangay_clearance' => ['label' => 'Barangay clearance', 'proves' => 'The barangay allows the business at this address.', 'who' => 'Every applicant', 'expiry' => 'required', 'match' => ['gym']],
        'facility_photo' => ['label' => 'Facility photos', 'proves' => 'The facility exists as described.', 'who' => 'Every Tier 2 applicant', 'max' => 30,
            // Thumbnails are enough; every other file must be opened before the checklist (R-132).
            'must_open' => false],
        'video_tour' => ['label' => 'Video tour', 'proves' => 'The facility and equipment are real and current.', 'who' => 'Every Tier 2 applicant', 'kind' => 'video'],
        'owner_id' => ['label' => "Owner's valid ID", 'proves' => 'Who the owner is.', 'who' => 'The owner', 'expiry' => 'optional', 'match' => ['owner']],
        'owner_selfie' => ['label' => 'Owner selfie', 'proves' => 'The owner is the person on the ID.', 'who' => 'The owner'],
        'utility_bill' => ['label' => 'Recent electricity or water bill', 'proves' => 'The business runs at the declared address.', 'who' => 'Every Tier 2 applicant'],
    ],

    // What each kind of file may be. Sizes are in kilobytes (Laravel "max" rule).
    'kinds' => [
        'document' => ['mimes' => ['pdf', 'jpg', 'jpeg', 'png'], 'max_kb' => 10 * 1024],
        'video' => ['mimes' => ['mp4'], 'max_kb' => 150 * 1024],
    ],

    'tiers' => [
        1 => ['label' => 'Tier 1', 'badge' => 'Tier 1 verified', 'requirements' => $tier1],
        2 => ['label' => 'Tier 2', 'badge' => 'Tier 2 verified', 'requirements' => [
            ...$tier1,
            ['types' => ['facility_photo'], 'min' => 10],
            ['types' => ['video_tour'], 'min' => 1],
            ['types' => ['owner_id'], 'min' => 1],
            ['types' => ['owner_selfie'], 'min' => 1],
            ['types' => ['utility_bill'], 'min' => 1],
        ]],
    ],

    // Administrator checklist (spec page 11, section 2.3).
    'checklist' => [
        'documents_complete' => ['label' => 'Every required document is present and readable', 'tiers' => [1, 2]],
        'permits_current' => ['label' => 'The permits are current', 'tiers' => [1, 2]],
        'details_match' => ['label' => 'Business name and address match across documents', 'tiers' => [1, 2]],
        'selfie_matches_id' => ['label' => 'The selfie matches the ID', 'tiers' => [2]],
        'facility_matches' => ['label' => 'Photos and video match the declared facility', 'tiers' => [2]],
    ],

    'upload_rules' => [
        'Documents must be clear and in colour.',
        'Documents must not be expired.',
        'Names and addresses must match across all documents.',
    ],

    'disciplines' => [
        'boxing' => 'Boxing',
        'muay_thai' => 'Muay Thai',
        'kickboxing' => 'Kickboxing',
        'mma' => 'Mixed martial arts',
        'bjj' => 'Brazilian jiu-jitsu',
        'wrestling' => 'Wrestling',
        'judo' => 'Judo',
        'karate' => 'Karate',
        'taekwondo' => 'Taekwondo',
        'arnis' => 'Arnis',
    ],
];
