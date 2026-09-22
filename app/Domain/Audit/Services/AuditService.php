<?php

namespace App\Domain\Audit\Services;

use App\Domain\Audit\Models\AuditLog;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditService
{
    /**
     * Record an immutable audit log entry.
     *
     * @param  array<string, mixed>|null  $oldValues
     * @param  array<string, mixed>|null  $newValues
     */
    public function log(
        string $event,
        ?Model $auditable = null,
        ?array $oldValues = null,
        ?array $newValues = null,
        ?User $actor = null,
        ?string $ipAddress = null,
        ?string $userAgent = null
    ): AuditLog {
        $actorId = $actor ? $actor->id : (Auth::check() ? Auth::id() : null);
        $ip = $ipAddress ?? (Request::ip() ?? '127.0.0.1');
        $agent = $userAgent ?? (Request::userAgent() ?? 'CLI/Internal');

        return AuditLog::create([
            'actor_user_id' => $actorId,
            'event' => $event,
            'auditable_type' => $auditable ? get_class($auditable) : null,
            'auditable_id' => $auditable?->getKey(),
            'old_values' => $oldValues,
            'new_values' => $newValues,
            'ip_address' => $ip,
            'user_agent' => $agent,
        ]);
    }

    /**
     * Verify the cryptographic integrity of the entire audit log chain.
     *
     * @return array{valid: bool, checked_count: int, broken_at_id: int|null}
     */
    public function verifyChainIntegrity(): array
    {
        $logs = AuditLog::orderBy('id')->get();
        $expectedPreviousHash = str_repeat('0', 64);
        $checkedCount = 0;

        foreach ($logs as $log) {
            $checkedCount++;

            // 1. Verify previous hash pointer
            if ($log->previous_hash !== $expectedPreviousHash) {
                return [
                    'valid' => false,
                    'checked_count' => $checkedCount,
                    'broken_at_id' => $log->id,
                ];
            }

            // 2. Recompute current hash
            $payload = json_encode([
                'actor_user_id' => $log->actor_user_id,
                'event' => $log->event,
                'auditable_type' => $log->auditable_type,
                'auditable_id' => $log->auditable_id,
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'ip_address' => $log->ip_address,
                'created_at' => (string) $log->created_at,
            ]);

            $calculatedHash = hash('sha256', $expectedPreviousHash.$payload);

            if ($log->hash !== $calculatedHash) {
                return [
                    'valid' => false,
                    'checked_count' => $checkedCount,
                    'broken_at_id' => $log->id,
                ];
            }

            $expectedPreviousHash = $log->hash;
        }

        return [
            'valid' => true,
            'checked_count' => $checkedCount,
            'broken_at_id' => null,
        ];
    }
}
