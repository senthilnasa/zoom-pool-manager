<?php

namespace App\Domain\Zoom\Models;

use App\Domain\Scheduling\Models\ResourceReservation;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;

class ZoomResource extends Model
{
    use SoftDeletes;

    protected $table = 'zoom_resources';

    protected $fillable = [
        'public_id',
        'zoom_user_id',
        'managed',
        'status',
        'priority',
        'is_backup',
        'participant_capacity',
        'large_meeting_capacity',
        'webinar_capacity',
        'cloud_recording',
        'transcript',
        'ai_companion',
        'max_concurrent',
        'capabilities_checked_at',
        'daily_api_call_count',
        'daily_api_reset_at',
    ];

    protected $casts = [
        'managed' => 'boolean',
        'priority' => 'integer',
        'is_backup' => 'boolean',
        'participant_capacity' => 'integer',
        'large_meeting_capacity' => 'integer',
        'webinar_capacity' => 'integer',
        'cloud_recording' => 'boolean',
        'transcript' => 'boolean',
        'ai_companion' => 'boolean',
        'max_concurrent' => 'integer',
        'capabilities_checked_at' => 'datetime',
        'daily_api_call_count' => 'integer',
        'daily_api_reset_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ZoomResource $resource) {
            if (empty($resource->public_id)) {
                $resource->public_id = (string) new Ulid;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function getNameAttribute(): string
    {
        if ($this->zoomUser) {
            $fullName = trim(($this->zoomUser->first_name ?? '').' '.($this->zoomUser->last_name ?? ''));

            return ! empty($fullName) ? $fullName : ($this->zoomUser->email ?? 'Zoom Resource #'.$this->id);
        }

        return 'Zoom Resource #'.$this->id;
    }

    public function getCapacityAttribute(): int
    {
        return $this->participant_capacity;
    }

    /**
     * @return BelongsTo<ZoomUser, $this>
     */
    public function zoomUser(): BelongsTo
    {
        return $this->belongsTo(ZoomUser::class, 'zoom_user_id');
    }

    /**
     * @return BelongsToMany<ResourcePool, $this>
     */
    public function pools(): BelongsToMany
    {
        return $this->belongsToMany(ResourcePool::class, 'resource_pool_members', 'resource_id', 'pool_id')
            ->withPivot('priority')
            ->withTimestamps();
    }

    /**
     * @return HasMany<ResourceReservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(ResourceReservation::class, 'resource_id');
    }
}
