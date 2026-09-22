<?php

namespace App\Domain\Workflow\Models;

use App\Domain\Meetings\Models\Meeting;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Symfony\Component\Uid\Ulid;

/**
 * @property int $id
 * @property string $public_id
 * @property int $meeting_id
 * @property int $priority
 * @property string $status
 * @property Carbon|null $notified_at
 * @property Carbon|null $allocated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Meeting $meeting
 */
class WaitlistEntry extends Model
{
    protected $table = 'waitlist_entries';

    protected $fillable = [
        'public_id',
        'meeting_id',
        'priority',
        'status',
        'notified_at',
        'allocated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meeting_id' => 'integer',
            'priority' => 'integer',
            'notified_at' => 'datetime',
            'allocated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WaitlistEntry $entry): void {
            if (empty($entry->public_id)) {
                $entry->public_id = strtolower((string) Ulid::generate());
            }
        });
    }

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }

    /**
     * @param  Builder<WaitlistEntry>  $query
     * @return Builder<WaitlistEntry>
     */
    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', 'waiting');
    }

    /**
     * @param  Builder<WaitlistEntry>  $query
     * @return Builder<WaitlistEntry>
     */
    public function scopeOrderedByPriority(Builder $query): Builder
    {
        return $query->orderBy('priority', 'asc')->orderBy('created_at', 'asc');
    }
}
