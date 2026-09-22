<?php

namespace App\Domain\Operations\Models;

use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class DataExportRequest extends Model
{
    public $timestamps = false;

    protected $table = 'data_export_requests';

    protected $fillable = [
        'public_id',
        'user_id',
        'requested_by_user_id',
        'status',
        'file_path',
        'expires_at',
        'completed_at',
        'created_at',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'expires_at' => 'datetime',
        'completed_at' => 'datetime',
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (DataExportRequest $export) {
            if (empty($export->public_id)) {
                $export->public_id = (string) new Ulid;
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}
