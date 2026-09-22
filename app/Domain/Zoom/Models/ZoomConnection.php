<?php

namespace App\Domain\Zoom\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;

class ZoomConnection extends Model
{
    use SoftDeletes;

    protected $table = 'zoom_connections';

    protected $fillable = [
        'public_id',
        'name',
        'account_id',
        'client_id',
        'client_secret',
        'webhook_secret_token',
        'status',
        'granted_scopes',
        'last_sync_at',
        'last_success_at',
        'last_error',
        'last_error_at',
        'enabled',
    ];

    protected $casts = [
        'account_id' => 'encrypted',
        'client_id' => 'encrypted',
        'client_secret' => 'encrypted',
        'webhook_secret_token' => 'encrypted',
        'granted_scopes' => 'json',
        'last_sync_at' => 'datetime',
        'last_success_at' => 'datetime',
        'last_error_at' => 'datetime',
        'enabled' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (ZoomConnection $connection) {
            if (empty($connection->public_id)) {
                $connection->public_id = (string) new Ulid;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return HasMany<ZoomUser, $this>
     */
    public function zoomUsers(): HasMany
    {
        return $this->hasMany(ZoomUser::class, 'connection_id');
    }
}
