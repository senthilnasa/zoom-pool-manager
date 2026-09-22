<?php

namespace App\Domain\Workflow\Models;

use App\Domain\Meetings\Models\Meeting;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Symfony\Component\Uid\Ulid;

/**
 * @property int $id
 * @property string $public_id
 * @property int|null $workflow_rule_id
 * @property int $meeting_id
 * @property bool $matched
 * @property array<string, mixed>|null $actions_triggered
 * @property array<string, mixed>|null $logs
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WorkflowExecution extends Model
{
    protected $table = 'workflow_executions';

    protected $fillable = [
        'public_id',
        'workflow_rule_id',
        'meeting_id',
        'matched',
        'actions_triggered',
        'logs',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'workflow_rule_id' => 'integer',
            'meeting_id' => 'integer',
            'matched' => 'boolean',
            'actions_triggered' => 'array',
            'logs' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkflowExecution $execution): void {
            if (empty($execution->public_id)) {
                $execution->public_id = strtolower((string) Ulid::generate());
            }
        });
    }

    /**
     * @return BelongsTo<WorkflowRule, $this>
     */
    public function rule(): BelongsTo
    {
        return $this->belongsTo(WorkflowRule::class, 'workflow_rule_id');
    }

    /**
     * @return BelongsTo<Meeting, $this>
     */
    public function meeting(): BelongsTo
    {
        return $this->belongsTo(Meeting::class, 'meeting_id');
    }
}
