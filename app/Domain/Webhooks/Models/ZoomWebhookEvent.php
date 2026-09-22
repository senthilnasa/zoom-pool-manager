<?php

namespace App\Domain\Webhooks\Models;

use App\Domain\Zoom\Models\ZoomConnection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class ZoomWebhookEvent extends Model
{
    protected $table = 'zoom_webhook_events';

    protected $fillable = [
        'public_id',
        'connection_id',
        'event_id',
        'event_type',
        'payload',
        'signature_valid',
        'status',
        'attempts',
        'error_message',
        'processed_at',
        'ip_address',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'payload' => 'array',
        'signature_valid' => 'boolean',
        'attempts' => 'integer',
        'processed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ZoomWebhookEvent $event) {
            if (empty($event->public_id)) {
                $event->public_id = (string) new Ulid;
            }
        });
    }

    /**
     * @return BelongsTo<ZoomConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ZoomConnection::class, 'connection_id');
    }
}
