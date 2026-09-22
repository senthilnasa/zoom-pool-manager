<?php

use App\Domain\Audit\Services\AuditService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('audit log entries automatically compute cryptographic hash chain', function () {
    $service = new AuditService;

    $log1 = $service->log('test.event_one', null, null, ['key' => 'value1']);
    expect($log1->previous_hash)->toBe(str_repeat('0', 64))
        ->and($log1->hash)->not->toBeEmpty();

    $log2 = $service->log('test.event_two', null, null, ['key' => 'value2']);
    expect($log2->previous_hash)->toBe($log1->hash)
        ->and($log2->hash)->not->toBeEmpty();

    $log3 = $service->log('test.event_three', null, null, ['key' => 'value3']);
    expect($log3->previous_hash)->toBe($log2->hash);

    $verification = $service->verifyChainIntegrity();
    expect($verification['valid'])->toBeTrue()
        ->and($verification['checked_count'])->toBe(3);
});

test('audit logs cannot be updated or deleted', function () {
    $service = new AuditService;
    $log = $service->log('test.immutable', null, null, ['initial' => 'state']);

    expect(function () use ($log) {
        $log->event = 'tampered.event';
        $log->save();
    })->toThrow(RuntimeException::class, 'Audit logs are immutable and cannot be updated.');

    expect(function () use ($log) {
        $log->delete();
    })->toThrow(RuntimeException::class, 'Audit logs are immutable and cannot be manually deleted.');
});
