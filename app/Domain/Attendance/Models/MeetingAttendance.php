<?php

namespace App\Domain\Attendance\Models;

use App\Domain\Meetings\Models\Meeting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class MeetingAttendance extends Model
{
    protected $table = 'meeting_attendances';

    protected $fillable = [
        'public_id',
        'meeting_id',
        'zoom_meeting_id',
        'zoom_participant_id',
        'participant_name',
        'participant_email',
        'join_time',
        'leave_time',
        'duration_seconds',
        'attendance_percentage',
        'device_type',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'join_time' => 'datetime',
        'leave_time' => 'datetime',
        'duration_seconds' => 'integer',
        'attendance_percentage' => 'float',
    ];

    protected static function booted(): void
    {
        static::creating(function (MeetingAttendance $attendance) {
            if (empty($attendance->public_id)) {
                $attendance->public_id = (string) new Ulid;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }
}
