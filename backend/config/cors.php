<?php

$origins = array_values(array_filter(array_map('trim', explode(',', (string) env('ROUH_FRONTEND_URL', '')))));
if (!$origins && env('APP_ENV', 'production') !== 'production') {
    $origins = ['http://localhost:5173', 'http://127.0.0.1:5173', 'http://localhost:5174', 'http://127.0.0.1:5174'];
}

return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => $origins,
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 86400,
    'supports_credentials' => true,
];
