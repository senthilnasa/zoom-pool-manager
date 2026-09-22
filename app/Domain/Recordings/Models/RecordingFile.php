<?php

namespace App\Domain\Recordings\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class RecordingFile extends Model
{
    protected $table = 'recording_files';

    protected $fillable = [
        'public_id',
        'recording_id',
        'zoom_file_id',
        'file_type',
        'file_extension',
        'file_size_bytes',
        'play_url',
        'download_url',
        'status',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'file_size_bytes' => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (RecordingFile $file) {
            if (empty($file->public_id)) {
                $file->public_id = (string) new Ulid;
            }
        });
    }

    /**
     * @return BelongsTo<CloudRecording, $this>
     */
    public function recording(): BelongsTo
    {
        return $this->belongsTo(CloudRecording::class, 'recording_id');
    }
}
