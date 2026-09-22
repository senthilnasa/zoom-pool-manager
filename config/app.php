<?php

return [

    'name' => env('APP_NAME', 'Zoom Pool Manager'),

    'env' => env('APP_ENV', 'production'),

    'debug' => (bool) env('APP_DEBUG', false),

    'url' => env('APP_URL', 'http://localhost:8000'),

    'timezone' => env('APP_TIMEZONE', 'Asia/Kolkata'),

    'locale' => env('APP_LOCALE', 'en'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

    /*
    |--------------------------------------------------------------------------
    | ZPM Application Settings
    |--------------------------------------------------------------------------
    */
    'demo' => (bool) env('ZPM_DEMO', false),
    'organization_name' => env('ZPM_ORGANIZATION_NAME', 'Reference University'),
    'pseudo_cron_token' => env('ZPM_PSEUDO_CRON_TOKEN'),
    'default_buffer_minutes' => (int) env('ZPM_DEFAULT_BUFFER_MINUTES', 10),
];
