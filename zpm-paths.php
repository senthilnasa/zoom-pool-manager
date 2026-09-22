<?php

/**
 * Zoom Pool Manager (ZPM) — Path Configuration
 *
 * Allows hosting environments (specifically shared hosting like cPanel/Plesk)
 * to locate the .env configuration file safely outside of the public web root.
 *
 * If 'env_path' is null, ZPM defaults to the project root dirname(__DIR__).
 */

return [
    'env_path' => env('ZPM_ENV_PATH', null),
    'env_file' => env('ZPM_ENV_FILE', '.env'),
];
