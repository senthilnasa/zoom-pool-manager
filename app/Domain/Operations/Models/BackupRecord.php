<?php

namespace App\Domain\Operations\Models;

use Illuminate\Database\Eloquent\Model;
use Symfony\Component\Uid\Ulid;

/**
 * @property int $id
 * @property string $public_id
 * @property string $filename
 * @property string $file_path
 * @property int $file_size_bytes
 * @property string $storage_disk
 * @property string $status
 * @property bool $is_encrypted
 * @property string|null $checksum
 */
class BackupRecord extends Model
{
    public $timestamps = false;

    protected $table = 'backup_records';

    protected $fillable = [
        'public_id',
        'filename',
        'file_path',
        'file_size_bytes',
        'storage_disk',
        'status',
        'is_encrypted',
        'checksum',
        'verified_at',
        'error_message',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'file_size_bytes' => 'integer',
        'is_encrypted' => 'boolean',
        'verified_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (BackupRecord $backup) {
            if (empty($backup->public_id)) {
                $backup->public_id = (string) new Ulid;
            }
        });
    }
}
