<?php

use App\Domain\Audit\Services\LogRedactionProcessor;
use Monolog\Level;
use Monolog\LogRecord;

test('redacts sensitive keys in context and extra array', function () {
    $processor = new LogRedactionProcessor;

    $context = [
        'user_id' => 123,
        'email' => 'admin@example.edu',
        'password' => 'secret_password_123',
        'client_secret' => 'zoom_oauth_client_secret',
        'access_token' => 'eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9',
        'passcode' => '998877',
        'host_key' => '123456',
        'nested' => [
            'webhook_secret_token' => 'wh_secret_xyz',
            'safe_param' => 'normal_value',
        ],
    ];

    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'zoom',
        level: Level::Info,
        message: 'Syncing Zoom user with token and credentials',
        context: $context,
        extra: ['mfa_secret' => 'BASE32SECRET']
    );

    $processed = $processor($record);

    expect($processed->context['user_id'])->toBe(123)
        ->and($processed->context['email'])->toBe('admin@example.edu')
        ->and($processed->context['password'])->toBe('[REDACTED]')
        ->and($processed->context['client_secret'])->toBe('[REDACTED]')
        ->and($processed->context['access_token'])->toBe('[REDACTED]')
        ->and($processed->context['passcode'])->toBe('[REDACTED]')
        ->and($processed->context['host_key'])->toBe('[REDACTED]')
        ->and($processed->context['nested']['webhook_secret_token'])->toBe('[REDACTED]')
        ->and($processed->context['nested']['safe_param'])->toBe('normal_value')
        ->and($processed->extra['mfa_secret'])->toBe('[REDACTED]');
});

test('redacts bearer tokens and zoom start urls inside log messages', function () {
    $processor = new LogRedactionProcessor;

    $record = new LogRecord(
        datetime: new DateTimeImmutable,
        channel: 'zoom',
        level: Level::Info,
        message: 'API Call with Bearer eyJhbGciOiJIUzI1NiJ9 and start URL https://us02web.zoom.us/s/123456789?zak=eyJhbGciOi'
    );

    $processed = $processor($record);

    expect($processed->message)->toContain('Bearer [REDACTED]')
        ->and($processed->message)->toContain('https://zoom.us/s/[REDACTED_START_URL]')
        ->and($processed->message)->not->toContain('zak=');
});
