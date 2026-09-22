<?php

namespace App\Domain\Workflow\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $quota_id
 * @property int $period_year
 * @property int $period_month
 * @property int $meetings_count
 * @property int $minutes_used
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Quota $quota
 */
class QuotaUsage extends Model
{
    protected $table = 'quota_usages';

    protected $fillable = [
        'quota_id',
        'period_year',
        'period_month',
        'meetings_count',
        'minutes_used',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quota_id' => 'integer',
            'period_year' => 'integer',
            'period_month' => 'integer',
            'meetings_count' => 'integer',
            'minutes_used' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Quota, $this>
     */
    public function quota(): BelongsTo
    {
        return $this->belongsTo(Quota::class, 'quota_id');
    }
}
