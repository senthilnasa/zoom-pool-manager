<?php

namespace App\Domain\Scheduling\Models;

use App\Domain\Users\Models\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Symfony\Component\Uid\Ulid;

class BookingPolicy extends Model
{
    protected $table = 'booking_policies';

    protected $fillable = [
        'public_id',
        'name',
        'department_id',
        'min_notice_hours',
        'max_advance_days',
        'min_buffer_minutes',
        'default_buffer_minutes',
        'max_duration_minutes',
        'is_active',
    ];

    protected $casts = [
        'min_notice_hours' => 'integer',
        'max_advance_days' => 'integer',
        'min_buffer_minutes' => 'integer',
        'default_buffer_minutes' => 'integer',
        'max_duration_minutes' => 'integer',
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (BookingPolicy $policy) {
            if (empty($policy->public_id)) {
                $policy->public_id = (string) new Ulid;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }
}
