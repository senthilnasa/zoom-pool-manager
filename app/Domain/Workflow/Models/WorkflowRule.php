<?php

namespace App\Domain\Workflow\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Symfony\Component\Uid\Ulid;

/**
 * @property int $id
 * @property string $public_id
 * @property string $name
 * @property int $priority
 * @property array<string, mixed> $conditions
 * @property array<string, mixed> $actions
 * @property bool $is_enabled
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
class WorkflowRule extends Model
{
    protected $table = 'workflow_rules';

    protected $fillable = [
        'public_id',
        'name',
        'priority',
        'conditions',
        'actions',
        'is_enabled',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'priority' => 'integer',
            'conditions' => 'array',
            'actions' => 'array',
            'is_enabled' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (WorkflowRule $rule): void {
            if (empty($rule->public_id)) {
                $rule->public_id = strtolower((string) Ulid::generate());
            }
        });
    }

    /**
     * @param  Builder<WorkflowRule>  $query
     * @return Builder<WorkflowRule>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /**
     * @param  Builder<WorkflowRule>  $query
     * @return Builder<WorkflowRule>
     */
    public function scopeOrderedByPriority(Builder $query): Builder
    {
        return $query->orderBy('priority', 'asc');
    }

    /**
     * @return HasMany<WorkflowExecution, $this>
     */
    public function executions(): HasMany
    {
        return $this->hasMany(WorkflowExecution::class, 'workflow_rule_id');
    }
}
