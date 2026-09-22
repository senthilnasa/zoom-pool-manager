<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Zoom\Models\ResourcePool;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Symfony\Component\Uid\Ulid;

class MeetingTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'meeting_templates';

    protected $fillable = [
        'public_id',
        'name',
        'code',
        'description',
        'security_profile_id',
        'default_pool_id',
        'default_duration_minutes',
        'max_duration_minutes',
        'max_participants',
        'requires_approval',
        'recording_mode',
        'ai_companion_policy',
        'series_mode',
        'is_active',
    ];

    protected $casts = [
        'default_duration_minutes' => 'integer',
        'max_duration_minutes' => 'integer',
        'max_participants' => 'integer',
        'requires_approval' => 'boolean',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (MeetingTemplate $template) {
            if (empty($template->public_id)) {
                $template->public_id = (string) new Ulid;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<SecurityProfile, $this>
     */
    public function securityProfile(): BelongsTo
    {
        return $this->belongsTo(SecurityProfile::class);
    }

    /**
     * @return BelongsTo<ResourcePool, $this>
     */
    public function defaultPool(): BelongsTo
    {
        return $this->belongsTo(ResourcePool::class, 'default_pool_id');
    }
}
