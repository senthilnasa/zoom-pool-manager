<?php

namespace App\Domain\Api\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property int $subscription_id
 * @property string $event_type
 * @property array<string, mixed> $payload
 * @property string $signature
 * @property int|null $response_status
 * @property string|null $response_body
 * @property int $attempt
 * @property string $status
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read WebhookSubscription $subscription
 */
class WebhookDelivery extends Model
{
    protected $table = 'webhook_deliveries';

    protected $fillable = [
        'public_id',
        'subscription_id',
        'event_type',
        'payload',
        'signature',
        'response_status',
        'response_body',
        'attempt',
        'status',
        'delivered_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'response_status' => 'integer',
        'attempt' => 'integer',
        'delivered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebhookDelivery $model) {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    /**
     * @return BelongsTo<WebhookSubscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'subscription_id');
    }
}
