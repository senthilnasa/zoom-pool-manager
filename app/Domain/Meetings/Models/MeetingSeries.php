<?php

namespace App\Domain\Meetings\Models;

use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ZoomResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;

class MeetingSeries extends Model
{
    use SoftDeletes;

    protected $table = 'meeting_series';

    protected $fillable = [
        'public_id',
        'requester_user_id',
        'owner_user_id',
        'department_id',
        'rrule',
        'timezone',
        'start_date',
        'until_date',
        'occurrence_count',
        'series_mode',
        'recording_mode',
        'waiting_room',
        'join_before_host',
        'jbh_time',
        'attendance_tracking',
        'share_host_key',
        'status',
        'zoom_meeting_id',
        'zoom_resource_id',
        'term_id',
        'source',
        'custom_fields',
    ];

    protected $casts = [
        'start_date' => 'date',
        'until_date' => 'date',
        'occurrence_count' => 'integer',
        'waiting_room' => 'boolean',
        'join_before_host' => 'boolean',
        'jbh_time' => 'integer',
        'attendance_tracking' => 'boolean',
        'share_host_key' => 'boolean',
        'custom_fields' => 'array',
    ];

    protected static function booted(): void
    {
        static::creating(function (MeetingSeries $series) {
            if (empty($series->public_id)) {
                $series->public_id = (string) new Ulid;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<ZoomResource, $this>
     */
    public function zoomResource(): BelongsTo
    {
        return $this->belongsTo(ZoomResource::class, 'zoom_resource_id');
    }

    /**
     * @return HasMany<Meeting, $this>
     */
    public function meetings(): HasMany
    {
        return $this->hasMany(Meeting::class, 'series_id');
    }
}
