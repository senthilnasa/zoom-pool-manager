<?php

namespace App\Domain\Zoom\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class ZoomUser extends Model
{
    protected $table = 'zoom_users';

    protected $fillable = [
        'connection_id',
        'zoom_user_id',
        'email',
        'first_name',
        'last_name',
        'user_type',
        'status',
        'timezone',
        'host_key',
        'synced_at',
        'raw_metadata',
    ];

    protected $casts = [
        'user_type' => 'integer',
        'host_key' => 'encrypted',
        'synced_at' => 'datetime',
        'raw_metadata' => 'json',
    ];

    /**
     * @return BelongsTo<ZoomConnection, $this>
     */
    public function connection(): BelongsTo
    {
        return $this->belongsTo(ZoomConnection::class, 'connection_id');
    }

    /**
     * @return HasOne<ZoomResource, $this>
     */
    public function resource(): HasOne
    {
        return $this->hasOne(ZoomResource::class, 'zoom_user_id');
    }
}
