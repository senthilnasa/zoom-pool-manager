<?php

namespace App\Domain\Zoom\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;

class ResourcePool extends Model
{
    use SoftDeletes;

    protected $table = 'resource_pools';

    protected $fillable = [
        'public_id',
        'name',
        'code',
        'description',
        'pool_strategy',
        'is_emergency_pool',
        'is_active',
    ];

    protected $casts = [
        'is_emergency_pool' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (ResourcePool $pool) {
            if (empty($pool->public_id)) {
                $pool->public_id = (string) new Ulid;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsToMany<ZoomResource, $this>
     */
    public function resources(): BelongsToMany
    {
        return $this->belongsToMany(ZoomResource::class, 'resource_pool_members', 'pool_id', 'resource_id')
            ->withPivot('priority')
            ->withTimestamps();
    }
}
