<?php

return [
    'paths' => ['api/*'],
    'allowed_methods' => ['*'],
    // Prefer explicit origins. Avoid CORS_ALLOWED_ORIGINS=* with credentials in production.
    'allowed_origins' => array_values(array_filter(array_map('trim', explode(',', env('CORS_ALLOWED_ORIGINS', 'http://localhost:5173'))))),
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    // JWT Bearer auth does not require credentialed cookies.
    'supports_credentials' => false,
];
