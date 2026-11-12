<?php

// R-114: receipt and delivery proof files kept on purchase orders (private disk).
return [
    'proofs' => [
        'mimes' => ['jpg', 'jpeg', 'png', 'webp', 'pdf'],
        'max_kb' => 5120,
        'max_files' => 10,
    ],
];
