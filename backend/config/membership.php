<?php

// Membership application form (R-31, R-32). The screens read these through GET /api/membership-form.
return [
    // R-32: a short health questionnaire. Each answer is yes or no; details go in one notes field.
    'health_questions' => [
        'heart_condition' => 'Do you have a heart condition or high blood pressure?',
        'chest_pain' => 'Have you had chest pain, fainting or dizziness during exercise?',
        'recent_injury' => 'Do you have a current injury, or one from the last 6 months?',
        'surgery' => 'Have you had surgery in the last 12 months?',
        'head_injury' => 'Have you had a concussion or head injury?',
        'medication' => 'Do you take medicine that could affect training?',
        'pregnant' => 'Are you pregnant?',
    ],

    // R-32: the same three waivers for every gym. The gym's own policies are shown beside them.
    'waivers' => [
        'assumption_of_risk' => 'I understand that combat sports carry a risk of injury, and I take part at my own risk.',
        'liability' => 'I will not hold the gym or its staff responsible for injuries that happen during normal training.',
        'emergency_consent' => 'If I am hurt and cannot decide for myself, the gym may get me emergency medical care.',
    ],

    'emergency_contacts_max' => 3,

    // R-32: the optional medical clearance, kept privately (as in R-15).
    'medical_file' => [
        'required' => true, // (R-136)
        'mimes' => ['pdf', 'jpg', 'jpeg', 'png'],
        'max_kb' => 5120,
    ],

    // R-34: desk payment methods. PayMongo payments are recorded only by the system (R-35).
    'desk_methods' => [
        'cash' => 'Cash',
        'card' => 'Card',
        'ewallet' => 'E-wallet',
    ],

    // R-37 to R-39.
    'entry_types' => [
        'promotion' => 'Promotion',
        'assessment' => 'Skill assessment',
        'competition' => 'Competition',
        'achievement' => 'Achievement',
    ],
];
