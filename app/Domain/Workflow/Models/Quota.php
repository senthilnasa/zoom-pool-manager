<?php

namespace App\Domain\Workflow\Models;

use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Symfony\Component\Uid\Ulid;

/**
 * @property int $id
 * @property string $public_id
 * @property string $scope_type
 * @property int $scope_id
 * @property int|null $max_meetings_per_month
 * @property int|null $max_hours_per_month
 * @property bool $is_active
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class Quota extends Model
{
    protected $table = 'quotas';

    protected $fillable = [
        'public_id',
        'scope_type',
        'scope_id',
        'max_meetings_per_month',
        'max_hours_per_month',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scope_id' => 'integer',
            'max_meetings_per_month' => 'integer',
            'max_hours_per_month' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Quota $quota): void {
            if (empty($quota->public_id)) {
                $quota->public_id = strtolower((string) Ulid::generate());
            }
        });
    }

    /**
     * @param  Builder<Quota>  $query
     * @return Builder<Quota>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * @return HasMany<QuotaUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(QuotaUsage::class, 'quota_id');
    }

    /**
     * @return BelongsTo<User, $this>|null
     */
    public function user(): ?BelongsTo
    {
        if ($this->scope_type === 'user') {
            return $this->belongsTo(User::class, 'scope_id');
        }

        return null;
    }

    /**
     * @return BelongsTo<Department, $this>|null
     */
    public function department(): ?BelongsTo
    {
        if ($this->scope_type === 'department') {
            return $this->belongsTo(Department::class, 'scope_id');
        }

        return null;
    }
}
