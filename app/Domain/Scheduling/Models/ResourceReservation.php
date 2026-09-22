<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Zoom\Models\ZoomResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class ResourceReservation extends Model
{
    protected $table = 'resource_reservations';

    protected $fillable = [
        'public_id',
        'resource_id',
        'meeting_id',
        'source',
        'occupied_from',
        'occupied_until',
        'status',
        'hold_expires_at',
    ];

    protected $casts = [
        'occupied_from' => 'datetime',
        'occupied_until' => 'datetime',
        'hold_expires_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ResourceReservation $reservation) {
            if (empty($reservation->public_id)) {
                $reservation->public_id = (string) new Ulid;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<ZoomResource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(ZoomResource::class, 'resource_id');
    }
}
