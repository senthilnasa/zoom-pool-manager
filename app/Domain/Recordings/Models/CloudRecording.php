<?php

namespace App\Domain\Recordings\Models;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\User;
use App\Domain\Zoom\Models\ZoomResource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;

class CloudRecording extends Model
{
    use SoftDeletes;

    protected $table = 'cloud_recordings';

    protected $fillable = [
        'public_id',
        'meeting_id',
        'zoom_resource_id',
        'logical_owner_user_id',
        'zoom_meeting_id',
        'zoom_recording_id',
        'topic',
        'storage_provider',
        'recording_start',
        'recording_end',
        'duration_minutes',
        'file_size_bytes',
        'share_url',
        'play_url',
        'download_url',
        'passcode',
        'status',
        'expires_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'recording_start' => 'datetime',
        'recording_end' => 'datetime',
        'expires_at' => 'datetime',
        'duration_minutes' => 'integer',
        'file_size_bytes' => 'integer',
        'passcode' => 'encrypted',
    ];

    protected static function booted(): void
    {
        static::creating(function (CloudRecording $recording) {
            if (empty($recording->public_id)) {
                $recording->public_id = (string) new Ulid;
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
    public function logicalOwner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'logical_owner_user_id');
    }

    /**
     * @return HasMany<RecordingFile, $this>
     */
    public function files(): HasMany
    {
        return $this->hasMany(RecordingFile::class, 'recording_id');
    }

    /**
     * @return HasMany<RecordingAccessLog, $this>
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(RecordingAccessLog::class, 'recording_id');
    }
}
