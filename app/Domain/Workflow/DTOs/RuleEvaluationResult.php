<?php

namespace App\Domain\Workflow\DTOs;

use App\Domain\Workflow\Models\WorkflowRule;
use Illuminate\Support\Collection;

class RuleEvaluationResult
{
    /**
     * @param  Collection<int, WorkflowRule>  $matchedRules
     * @param  array<int, array<string, mixed>>  $approvalSteps
     * @param  array<int, string>  $logs
     */
    public function __construct(
        public Collection $matchedRules,
        public bool $isAutoApproved = false,
        public bool $isRejected = false,
        public ?string $rejectReason = null,
        public bool $requiresApproval = false,
        public array $approvalSteps = [],
        public ?int $overridePoolId = null,
        public ?int $overrideProfileId = null,
        public ?string $overrideRecordingMode = null,
        public array $logs = []
    ) {}
}
