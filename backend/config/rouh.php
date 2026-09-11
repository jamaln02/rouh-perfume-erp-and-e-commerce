<?php

return [
    'accounting' => [
        // The first accounting day for this installation. Opening balances are
        // intentionally empty until the administrator enters them manually.
        'start_date' => null,
        // IFRS 15-aligned default for made-to-order sales. Change only when documented
        // contract terms support a different control-transfer point.
        'revenue_recognition_status' => env('ROUH_REVENUE_RECOGNITION_STATUS', 'delivered'),
    ],
    'order' => [
        'max_quantity_per_line' => (int) env('ROUH_MAX_ORDER_LINE_QTY', env('ROUH_MAX_ORDER_QTY', 100)),
        'max_consumption_variance_percent' => (float) env('ROUH_MAX_CONSUMPTION_VARIANCE_PERCENT', 10),
    ],
    'auth' => [
        'cookie_name' => env('ROUH_AUTH_COOKIE', 'rouh_auth'),
        'cookie_minutes' => (int) env('ROUH_AUTH_COOKIE_MINUTES', 60 * 24 * 7),
        'cookie_domain' => env('ROUH_AUTH_COOKIE_DOMAIN'),
        'cookie_same_site' => env('ROUH_AUTH_COOKIE_SAME_SITE', env('APP_ENV', 'production') === 'production' ? 'none' : 'lax'),
    ],
    'contact' => [
        'whatsapp_number' => '+963933898625',
    ],
    'opening_inventory' => [
        'allow_commit_in_production' => (bool) env('ROUH_ALLOW_OPENING_IMPORT', false),
    ],
    'ledger_rebuild' => [
        'allow_mutation' => (bool) env('ROUH_ALLOW_LEDGER_REBUILD', false),
    ],
];
