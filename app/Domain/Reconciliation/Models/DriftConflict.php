<?php

namespace App\Domain\Reconciliation\Models;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ZoomResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class DriftConflict extends Model
{
    protected $table = 'drift_conflicts';

    protected $fillable = [
        'public_id',
        'incident_type',
        'meeting_id',
        'zoom_resource_id',
        'zoom_meeting_id',
        'details',
        'status',
        'resolved_by_user_id',
        'resolved_at',
        'resolution_notes',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'details' => 'array',
        'resolved_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DriftConflict $conflict) {
            if (empty($conflict->public_id)) {
                $conflict->public_id = (string) new Ulid;
            }
        });
    }

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    /**
     * @return BelongsTo<ZoomResource, $this>
     */
    public function resource(): BelongsTo
    {
        return $this->belongsTo(ZoomResource::class, 'zoom_resource_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id');
    }
}
