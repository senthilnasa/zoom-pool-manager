<?php

namespace App\Domain\Api\Models;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property string $url
 * @property string $secret
 * @property array<string> $events
 * @property bool $is_active
 * @property int $failure_count
 * @property Carbon|null $last_delivered_at
 * @property int|null $created_by_user_id
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read User|null $creator
 * @property-read Collection<int, WebhookDelivery> $deliveries
 */
class WebhookSubscription extends Model
{
    protected $table = 'webhook_subscriptions';

    protected $fillable = [
        'public_id',
        'name',
        'url',
        'secret',
        'events',
        'is_active',
        'failure_count',
        'last_delivered_at',
        'created_by_user_id',
    ];

    protected $casts = [
        'secret' => 'encrypted',
        'events' => 'array',
        'is_active' => 'boolean',
        'failure_count' => 'integer',
        'last_delivered_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (WebhookSubscription $model) {
            if (empty($model->public_id)) {
                $model->public_id = (string) Str::ulid();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return HasMany<WebhookDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(WebhookDelivery::class, 'subscription_id');
    }

    public function subscribesTo(string $event): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if (in_array('*', $this->events ?? [], true)) {
            return true;
        }

        return in_array($event, $this->events ?? [], true);
    }
}
