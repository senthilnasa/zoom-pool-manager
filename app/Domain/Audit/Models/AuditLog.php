<?php

namespace App\Domain\Audit\Models;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use RuntimeException;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $table = 'audit_logs';

    protected $fillable = [
        'actor_user_id',
        'event',
        'auditable_type',
        'auditable_id',
        'old_values',
        'new_values',
        'ip_address',
        'user_agent',
        'previous_hash',
        'hash',
        'created_at',
    ];

    protected $casts = [
        'old_values' => 'json',
        'new_values' => 'json',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        // Enforce immutability: prevent updating or deleting audit logs
        static::updating(function () {
            throw new RuntimeException('Audit logs are immutable and cannot be updated.');
        });

        static::deleting(function () {
            if (! app()->runningInConsole() || ! defined('ZPM_PURGING_AUDIT_LOGS')) {
                throw new RuntimeException('Audit logs are immutable and cannot be manually deleted.');
            }
        });

        // Compute hash chain on creation
        static::creating(function (AuditLog $log) {
            $log->created_at = $log->created_at ?? now();

            $lastLog = static::orderByDesc('id')->first();
            $previousHash = $lastLog ? $lastLog->hash : str_repeat('0', 64);
            $log->previous_hash = $previousHash;

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

            $log->hash = hash('sha256', $previousHash.$payload);
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
