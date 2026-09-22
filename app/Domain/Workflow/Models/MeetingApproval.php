<?php

namespace App\Domain\Workflow\Models;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Symfony\Component\Uid\Ulid;

/**
 * @property int $id
 * @property string $public_id
 * @property int $meeting_id
 * @property int $step
 * @property int $approver_user_id
 * @property int|null $delegated_from_user_id
 * @property string $decision
 * @property string|null $decision_notes
 * @property Carbon|null $decided_at
 * @property Carbon|null $due_at
 * @property Carbon|null $reminder_sent_at
 * @property Carbon|null $escalated_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Meeting $meeting
 * @property-read User $approver
 * @property-read User|null $delegatedFrom
 */
class MeetingApproval extends Model
{
    protected $table = 'meeting_approvals';

    protected $fillable = [
        'public_id',
        'meeting_id',
        'step',
        'approver_user_id',
        'delegated_from_user_id',
        'decision',
        'decision_notes',
        'decided_at',
        'due_at',
        'reminder_sent_at',
        'escalated_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meeting_id' => 'integer',
            'step' => 'integer',
            'approver_user_id' => 'integer',
            'delegated_from_user_id' => 'integer',
            'decided_at' => 'datetime',
            'due_at' => 'datetime',
            'reminder_sent_at' => 'datetime',
            'escalated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MeetingApproval $approval): void {
            if (empty($approval->public_id)) {
                $approval->public_id = strtolower((string) Ulid::generate());
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
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function delegatedFrom(): BelongsTo
    {
        return $this->belongsTo(User::class, 'delegated_from_user_id');
    }

    /**
     * @param  Builder<MeetingApproval>  $query
     * @return Builder<MeetingApproval>
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('decision', 'pending');
    }
}
