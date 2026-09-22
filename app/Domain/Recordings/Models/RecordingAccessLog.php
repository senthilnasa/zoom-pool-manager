<?php

namespace App\Domain\Recordings\Models;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RecordingAccessLog extends Model
{
    public $timestamps = false;

    protected $table = 'recording_access_logs';

    protected $fillable = [
        'recording_id',
        'user_id',
        'action',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'created_at' => 'datetime',
    ];

    /**
     * @return BelongsTo<CloudRecording, $this>
     */
    public function recording(): BelongsTo
    {
        return $this->belongsTo(CloudRecording::class, 'recording_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
