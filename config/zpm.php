<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Version & Metadata
    |--------------------------------------------------------------------------
    | Single source of truth for the Zoom Pool Manager application version.
    */
    'version' => env('ZPM_VERSION', (function () {
        $versionFile = dirname(__DIR__).'/version.json';
        if (file_exists($versionFile)) {
            $data = json_decode((string) file_get_contents($versionFile), true);
            if (! empty($data['version']) && is_string($data['version'])) {
                return (string) $data['version'];
            }
        }

        return '1.0.23';
    })()),

    /*
    |--------------------------------------------------------------------------
    | Official GitHub Repository Details
    |--------------------------------------------------------------------------
    */
    'repository' => 'senthilnasa/zoom-pool-manager',
    'repository_url' => 'https://github.com/senthilnasa/zoom-pool-manager',
    'release_api_url' => 'https://api.github.com/repos/senthilnasa/zoom-pool-manager/releases/latest',
    'github_token' => env('ZPM_GITHUB_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Project Attribution
    |--------------------------------------------------------------------------
    */
    'attribution' => [
        'author' => 'Senthil Nasa',
        'url' => 'https://github.com/senthilnasa',
        'label' => 'Made with ❤️ by Senthil Nasa',
    ],

    /*
    |--------------------------------------------------------------------------
    | Auto-Update Configuration
    |--------------------------------------------------------------------------
    */
    'updates' => [
        'cache_ttl_seconds' => (int) env('ZPM_UPDATE_CACHE_TTL', 3600),
        'timeout_seconds' => 15,
        'lock_timeout_minutes' => 30,
        'download_timeout_seconds' => 300,
        'storage_path' => storage_path('app/updates'),
        'backup_before_update' => true,
    ],
];
