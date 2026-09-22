<?php

namespace App\Domain\Operations\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\Ulid;

class Alert extends Model
{
    protected $table = 'alerts';

    protected $fillable = [
        'public_id',
        'key',
        'severity',
        'title',
        'message',
        'details',
        'first_seen_at',
        'last_seen_at',
        'count',
        'notified_at',
        'resolved_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'details' => 'array',
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'notified_at' => 'datetime',
        'resolved_at' => 'datetime',
        'count' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (Alert $alert) {
            if (empty($alert->public_id)) {
                $alert->public_id = (string) new Ulid;
            }
        });
    }

    public function isResolved(): bool
    {
        return $this->resolved_at !== null;
    }
}
