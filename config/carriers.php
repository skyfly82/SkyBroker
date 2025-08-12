<?php

return [
    'inpost' => [
        'api_url'              => env('INPOST_API_URL'),
        'token'                => env('INPOST_TOKEN'),
        'organization_id'      => env('INPOST_ORGANIZATION_ID'),
        // Tymczasowe na czas smoke-testu (później przeniesiemy do requestu)
        'sender_email'         => env('INPOST_SENDER_EMAIL', 'noreply@example.com'),
        'default_target_point' => env('INPOST_TARGET_POINT', 'WAW038'),
    ],
];
