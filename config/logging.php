<?php

use App\Domain\Audit\Services\LogRedactionProcessor;
use Monolog\Handler\NullHandler;

return [

    'default' => env('LOG_CHANNEL', 'stack'),

    'deprecations' => [
        'channel' => env('LOG_DEPRECATIONS_CHANNEL', 'null'),
        'trace' => env('LOG_DEPRECATIONS_TRACE', false),
    ],

    'channels' => [

        'stack' => [
            'driver' => 'stack',
            'channels' => ['single'],
            'ignore_exceptions' => false,
        ],

        'single' => [
            'driver' => 'single',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'replace_placeholders' => true,
            'tap' => [LogRedactionProcessor::class],
        ],

        'daily' => [
            'driver' => 'daily',
            'path' => storage_path('logs/laravel.log'),
            'level' => env('LOG_LEVEL', 'info'),
            'days' => env('LOG_DAILY_DAYS', 14),
            'replace_placeholders' => true,
            'tap' => [LogRedactionProcessor::class],
        ],

        // Domain-specific logging channels (SPEC Part I)
        'auth' => [
            'driver' => 'daily',
            'path' => storage_path('logs/auth.log'),
            'level' => 'info',
            'tap' => [LogRedactionProcessor::class],
        ],

        'zoom' => [
            'driver' => 'daily',
            'path' => storage_path('logs/zoom.log'),
            'level' => 'info',
            'tap' => [LogRedactionProcessor::class],
        ],

        'meeting' => [
            'driver' => 'daily',
            'path' => storage_path('logs/meeting.log'),
            'level' => 'info',
            'tap' => [LogRedactionProcessor::class],
        ],

        'allocation' => [
            'driver' => 'daily',
            'path' => storage_path('logs/allocation.log'),
            'level' => 'info',
            'tap' => [LogRedactionProcessor::class],
        ],

        'workflow' => [
            'driver' => 'daily',
            'path' => storage_path('logs/workflow.log'),
            'level' => 'info',
            'tap' => [LogRedactionProcessor::class],
        ],

        'email' => [
            'driver' => 'daily',
            'path' => storage_path('logs/email.log'),
            'level' => 'info',
            'tap' => [LogRedactionProcessor::class],
        ],

        'webhook' => [
            'driver' => 'daily',
            'path' => storage_path('logs/webhook.log'),
            'level' => 'info',
            'tap' => [LogRedactionProcessor::class],
        ],

        'recording' => [
            'driver' => 'daily',
            'path' => storage_path('logs/recording.log'),
            'level' => 'info',
            'tap' => [LogRedactionProcessor::class],
        ],

        'job' => [
            'driver' => 'daily',
            'path' => storage_path('logs/job.log'),
            'level' => 'info',
            'tap' => [LogRedactionProcessor::class],
        ],

        'security' => [
            'driver' => 'daily',
            'path' => storage_path('logs/security.log'),
            'level' => 'info',
            'tap' => [LogRedactionProcessor::class],
        ],

        'api' => [
            'driver' => 'daily',
            'path' => storage_path('logs/api.log'),
            'level' => 'info',
            'tap' => [LogRedactionProcessor::class],
        ],

        'system' => [
            'driver' => 'daily',
            'path' => storage_path('logs/system.log'),
            'level' => 'info',
            'tap' => [LogRedactionProcessor::class],
        ],

        'null' => [
            'driver' => 'monolog',
            'handler' => NullHandler::class,
        ],

    ],

];
