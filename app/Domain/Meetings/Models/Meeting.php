<?php

namespace App\Domain\Meetings\Models;

use App\Domain\Attendance\Models\MeetingAttendance;
use App\Domain\Meetings\Services\MeetingStateMachine;
use App\Domain\Reconciliation\Models\DriftConflict;
use App\Domain\Recordings\Models\CloudRecording;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\SecurityProfile;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\Models\MeetingApproval;
use App\Domain\Workflow\Models\WaitlistEntry;
use App\Domain\Workflow\Models\WorkflowExecution;
use App\Domain\Zoom\Models\ZoomResource;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;

/**
 * @property int $id
 * @property string $public_id
 * @property string $title
 * @property string|null $description
 * @property string $meeting_type
 * @property string $status
 * @property Carbon|\Illuminate\Support\Carbon $starts_at
 * @property Carbon|\Illuminate\Support\Carbon $ends_at
 * @property int $buffer_minutes
 * @property int $participant_count
 * @property string|null $join_url
 * @property string|null $zoom_meeting_id
 * @property string|null $cancellation_reason
 * @property string $recording_mode
 * @property bool $waiting_room
 * @property bool $join_before_host
 * @property int $jbh_time
 * @property bool $attendance_tracking
 * @property bool $share_host_key
 * @property string|null $host_key
 * @property int $requester_user_id
 * @property int $owner_user_id
 * @property int|null $department_id
 * @property int|null $zoom_resource_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property \Illuminate\Support\Carbon|null $updated_at
 * @property-read User|null $owner
 * @property-read User|null $requester
 * @property-read Department|null $department
 * @property-read ZoomResource|null $zoomResource
 * @property-read Collection<int, MeetingInvitee> $invitees
 */
class Meeting extends Model
{
    use SoftDeletes;

    protected $table = 'meetings';

    protected $fillable = [
        'public_id',
        'series_id',
        'occurrence_index',
        'zoom_occurrence_id',
        'title',
        'description',
        'meeting_type',
        'starts_at',
        'ends_at',
        'timezone',
        'participant_count',
        'requester_user_id',
        'owner_user_id',
        'department_id',
        'template_id',
        'security_profile_id',
        'ai_companion_policy',
        'recording_mode',
        'waiting_room',
        'join_before_host',
        'jbh_time',
        'attendance_tracking',
        'share_host_key',
        'external_participants',
        'registration_enabled',
        'zoom_resource_id',
        'zoom_meeting_id',
        'zoom_uuid',
        'join_url',
        'passcode',
        'host_key',
        'link_distributed_at',
        'status',
        'idempotency_key',
        'source',
        'is_detached_from_series',
        'cancelled_reason',
    ];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'participant_count' => 'integer',
        'occurrence_index' => 'integer',
        'passcode' => 'encrypted',
        'link_distributed_at' => 'datetime',
        'waiting_room' => 'boolean',
        'join_before_host' => 'boolean',
        'jbh_time' => 'integer',
        'attendance_tracking' => 'boolean',
        'share_host_key' => 'boolean',
        'external_participants' => 'boolean',
        'registration_enabled' => 'boolean',
        'is_detached_from_series' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Meeting $meeting) {
            if (empty($meeting->public_id)) {
                $meeting->public_id = (string) new Ulid;
            }
            if (empty($meeting->idempotency_key)) {
                $meeting->idempotency_key = (string) new Ulid;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function getDurationMinutesAttribute(): int
    {
        return (int) $this->starts_at->diffInMinutes($this->ends_at);
    }

    public function canTransitionTo(string $toStatus): bool
    {
        return app(MeetingStateMachine::class)->canTransitionTo($this, $toStatus);
    }

    /**
     * @return BelongsTo<MeetingSeries, $this>
     */
    public function series(): BelongsTo
    {
        return $this->belongsTo(MeetingSeries::class, 'series_id');
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
     * @return BelongsTo<MeetingTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(MeetingTemplate::class, 'template_id');
    }

    /**
     * @return BelongsTo<SecurityProfile, $this>
     */
    public function securityProfile(): BelongsTo
    {
        return $this->belongsTo(SecurityProfile::class, 'security_profile_id');
    }

    /**
     * @return BelongsTo<ZoomResource, $this>
     */
    public function zoomResource(): BelongsTo
    {
        return $this->belongsTo(ZoomResource::class, 'zoom_resource_id');
    }

    /**
     * @return HasMany<MeetingInvitee, $this>
     */
    public function invitees(): HasMany
    {
        return $this->hasMany(MeetingInvitee::class, 'meeting_id');
    }

    /**
     * @return HasMany<MeetingStatusHistory, $this>
     */
    public function statusHistory(): HasMany
    {
        return $this->hasMany(MeetingStatusHistory::class, 'meeting_id');
    }

    /**
     * @return HasOne<MeetingRegistrationConfig, $this>
     */
    public function registrationConfig(): HasOne
    {
        return $this->hasOne(MeetingRegistrationConfig::class, 'meeting_id');
    }

    /**
     * @return HasMany<MeetingApproval, $this>
     */
    public function approvals(): HasMany
    {
        return $this->hasMany(MeetingApproval::class, 'meeting_id');
    }

    /**
     * @return HasMany<MeetingAttendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(MeetingAttendance::class, 'meeting_id');
    }

    /**
     * @return HasMany<WorkflowExecution, $this>
     */
    public function workflowExecutions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class, 'meeting_id');
    }

    /**
     * @return HasOne<WaitlistEntry, $this>
     */
    public function waitlistEntry(): HasOne
    {
        return $this->hasOne(WaitlistEntry::class, 'meeting_id');
    }

    /**
     * @return HasMany<CloudRecording, $this>
     */
    public function recordings(): HasMany
    {
        return $this->hasMany(CloudRecording::class, 'meeting_id');
    }

    /**
     * @return HasMany<DriftConflict, $this>
     */
    public function driftConflicts(): HasMany
    {
        return $this->hasMany(DriftConflict::class, 'meeting_id');
    }
}
