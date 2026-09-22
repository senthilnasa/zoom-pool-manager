<?php

namespace App\Domain\Workflow\Services;

use App\Domain\Meetings\Models\Meeting;
use App\Domain\Users\Models\User;
use App\Domain\Workflow\DTOs\RuleEvaluationResult;
use App\Domain\Workflow\Models\WorkflowExecution;
use App\Domain\Workflow\Models\WorkflowRule;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RuleEvaluationEngine
{
    /**
     * Evaluate an existing meeting against all active rules.
     *
     * @param  array<string, mixed>  $context
     */
    public function evaluate(Meeting $meeting, array $context = []): RuleEvaluationResult
    {
        $requester = $meeting->requester;
        $rules = WorkflowRule::enabled()->orderedByPriority()->get();

        $data = [
            'requester_id' => $meeting->requester_user_id,
            'department_id' => $meeting->department_id,
            'duration' => $meeting->duration_minutes,
            'participant_count' => $meeting->participant_count,
            'meeting_type' => $meeting->meeting_type,
            'recording_mode' => $meeting->recording_mode,
            'external_participants' => $meeting->external_participants,
            'security_profile_id' => $meeting->security_profile_id,
            'pool_id' => $meeting->zoomResource?->pools()->first()?->id,
            'template_id' => $meeting->template_id,
            'is_instant' => abs($meeting->starts_at->diffInMinutes(now())) <= 5,
            'starts_at' => $meeting->starts_at,
            'ends_at' => $meeting->ends_at,
        ];

        $result = $this->evaluateRules($rules, $data, $requester, $meeting);

        return $result;
    }

    /**
     * Test rules against draft input (for simulations / pre-submission).
     *
     * @param  array<string, mixed>  $data
     */
    public function evaluateDraft(array $data, User $requester): RuleEvaluationResult
    {
        $rules = WorkflowRule::enabled()->orderedByPriority()->get();

        $startsAt = isset($data['starts_at']) ? Carbon::parse($data['starts_at']) : Carbon::now();
        $endsAt = isset($data['ends_at']) ? Carbon::parse($data['ends_at']) : $startsAt->copy()->addHour();
        $duration = (int) $startsAt->diffInMinutes($endsAt);

        $normalized = [
            'requester_id' => $requester->id,
            'department_id' => $data['department_id'] ?? $requester->department_id,
            'duration' => $duration,
            'participant_count' => (int) ($data['participant_count'] ?? 10),
            'meeting_type' => $data['meeting_type'] ?? 'meeting',
            'recording_mode' => $data['recording_mode'] ?? 'none',
            'external_participants' => ! empty($data['external_participants']),
            'security_profile_id' => $data['security_profile_id'] ?? null,
            'pool_id' => $data['preferred_pool_id'] ?? null,
            'template_id' => $data['template_id'] ?? null,
            'is_instant' => ! empty($data['is_instant']) || abs($startsAt->diffInMinutes(now())) <= 5,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ];

        return $this->evaluateRules($rules, $normalized, $requester, null);
    }

    /**
     * Evaluate rules collection against normalized parameters.
     *
     * @param  Collection<int, WorkflowRule>  $rules
     * @param  array<string, mixed>  $data
     */
    protected function evaluateRules(
        Collection $rules,
        array $data,
        User $requester,
        ?Meeting $meeting = null
    ): RuleEvaluationResult {
        /** @var Collection<int, WorkflowRule> $matchedRules */
        $matchedRules = new Collection;
        $logs = [];
        $isAutoApproved = false;
        $isRejected = false;
        $rejectReason = null;
        $requiresApproval = false;
        $approvalSteps = [];
        $overridePoolId = null;
        $overrideProfileId = null;
        $overrideRecordingMode = null;

        foreach ($rules as $rule) {
            $conditions = $rule->conditions ?? [];
            $actions = $rule->actions ?? [];

            $isMatch = $this->matchesConditions($conditions, $data, $requester);

            if ($isMatch) {
                $matchedRules->push($rule);
                $logs[] = "Rule [{$rule->name}] (Priority: {$rule->priority}) matched.";

                // Process Actions
                if (! empty($actions['reject'])) {
                    $isRejected = true;
                    $rejectReason = is_string($actions['reject']) ? $actions['reject'] : 'Rejected by policy rule: '.$rule->name;
                    $logs[] = "Action: Rejected with reason: {$rejectReason}";
                }

                if (! empty($actions['auto_approve'])) {
                    $isAutoApproved = true;
                    $logs[] = 'Action: Auto-approved flag set.';
                }

                if (! empty($actions['require_approval'])) {
                    $requiresApproval = true;
                    $approvalConfig = $actions['require_approval'];
                    if (is_array($approvalConfig)) {
                        // If it defines multiple steps
                        if (isset($approvalConfig['steps']) && is_array($approvalConfig['steps'])) {
                            foreach ($approvalConfig['steps'] as $step) {
                                $approvalSteps[] = $step;
                            }
                        } else {
                            $approvalSteps[] = $approvalConfig;
                        }
                    } else {
                        $approvalSteps[] = ['approver_type' => 'manager', 'mode' => 'ANY'];
                    }
                    $logs[] = 'Action: Approval required.';
                }

                if (! empty($actions['assign_pool'])) {
                    $overridePoolId = (int) $actions['assign_pool'];
                    $logs[] = "Action: Pool override to ID {$overridePoolId}.";
                }

                if (! empty($actions['assign_profile'])) {
                    $overrideProfileId = (int) $actions['assign_profile'];
                    $logs[] = "Action: Security profile override to ID {$overrideProfileId}.";
                }

                if (! empty($actions['enable_recording'])) {
                    $overrideRecordingMode = (string) $actions['enable_recording'];
                    $logs[] = "Action: Recording override to {$overrideRecordingMode}.";
                }
            } else {
                $logs[] = "Rule [{$rule->name}] did not match conditions.";
            }

            // Record execution history if evaluating a persistent meeting
            if ($meeting !== null) {
                WorkflowExecution::create([
                    'workflow_rule_id' => $rule->id,
                    'meeting_id' => $meeting->id,
                    'matched' => $isMatch,
                    'actions_triggered' => $isMatch ? $actions : null,
                    'logs' => $logs,
                ]);
            }

            // If auto-approved or rejected, stop processing lower-priority rules
            if ($isRejected || $isAutoApproved) {
                break;
            }
        }

        // Auto-approve overrides approval requirement
        if ($isAutoApproved) {
            $requiresApproval = false;
            $approvalSteps = [];
        }

        return new RuleEvaluationResult(
            matchedRules: $matchedRules,
            isAutoApproved: $isAutoApproved,
            isRejected: $isRejected,
            rejectReason: $rejectReason,
            requiresApproval: $requiresApproval,
            approvalSteps: $approvalSteps,
            overridePoolId: $overridePoolId,
            overrideProfileId: $overrideProfileId,
            overrideRecordingMode: $overrideRecordingMode,
            logs: $logs
        );
    }

    /**
     * Check if meeting parameters satisfy all rule conditions.
     *
     * @param  array<string, mixed>  $conditions
     * @param  array<string, mixed>  $data
     */
    public function matchesConditions(array $conditions, array $data, User $requester): bool
    {
        // 1. Requester ID
        if (isset($conditions['requester_id'])) {
            $allowed = (array) $conditions['requester_id'];
            if (! in_array($requester->id, $allowed, false)) {
                return false;
            }
        }

        // 2. Role check
        if (isset($conditions['role'])) {
            $roles = (array) $conditions['role'];
            $hasRole = false;
            foreach ($roles as $role) {
                if ($requester->hasRole($role)) {
                    $hasRole = true;
                    break;
                }
            }
            if (! $hasRole) {
                return false;
            }
        }

        // 3. Department ID
        if (isset($conditions['department_id'])) {
            $allowedDepts = (array) $conditions['department_id'];
            if (empty($data['department_id']) || ! in_array($data['department_id'], $allowedDepts, false)) {
                return false;
            }
        }

        // 4. Duration Min / Max (in minutes)
        if (isset($conditions['duration_min']) && $data['duration'] < (int) $conditions['duration_min']) {
            return false;
        }
        if (isset($conditions['duration_max']) && $data['duration'] > (int) $conditions['duration_max']) {
            return false;
        }

        // 5. Participant Count Min / Max
        if (isset($conditions['participant_count_min']) && $data['participant_count'] < (int) $conditions['participant_count_min']) {
            return false;
        }
        if (isset($conditions['participant_count_max']) && $data['participant_count'] > (int) $conditions['participant_count_max']) {
            return false;
        }

        // 6. Meeting Type
        if (isset($conditions['meeting_type'])) {
            $allowedTypes = (array) $conditions['meeting_type'];
            if (! in_array($data['meeting_type'], $allowedTypes, true)) {
                return false;
            }
        }

        // 7. Recording Mode
        if (isset($conditions['recording_mode'])) {
            $allowedModes = (array) $conditions['recording_mode'];
            if (! in_array($data['recording_mode'], $allowedModes, true)) {
                return false;
            }
        }

        // 8. External Participants
        if (isset($conditions['external_participants'])) {
            if ((bool) $conditions['external_participants'] !== (bool) $data['external_participants']) {
                return false;
            }
        }

        // 9. Security Profile ID
        if (isset($conditions['security_profile_id'])) {
            $allowedProfiles = (array) $conditions['security_profile_id'];
            if (empty($data['security_profile_id']) || ! in_array($data['security_profile_id'], $allowedProfiles, false)) {
                return false;
            }
        }

        // 10. Pool ID
        if (isset($conditions['pool_id'])) {
            $allowedPools = (array) $conditions['pool_id'];
            if (empty($data['pool_id']) || ! in_array($data['pool_id'], $allowedPools, false)) {
                return false;
            }
        }

        // 11. Template ID
        if (isset($conditions['template_id'])) {
            $allowedTemplates = (array) $conditions['template_id'];
            if (empty($data['template_id']) || ! in_array($data['template_id'], $allowedTemplates, false)) {
                return false;
            }
        }

        // 12. Instant Meeting
        if (isset($conditions['is_instant'])) {
            if ((bool) $conditions['is_instant'] !== (bool) $data['is_instant']) {
                return false;
            }
        }

        // 13. Working Hours Only (Monday-Friday, 08:00 - 18:00)
        if (! empty($conditions['working_hours_only'])) {
            /** @var CarbonInterface $startsAt */
            $startsAt = $data['starts_at'];
            /** @var CarbonInterface $endsAt */
            $endsAt = $data['ends_at'];

            if ($startsAt->isWeekend() || $endsAt->isWeekend()) {
                return false;
            }
            if ($startsAt->hour < 8 || $endsAt->hour > 18 || ($endsAt->hour === 18 && $endsAt->minute > 0)) {
                return false;
            }
        }

        return true;
    }
}
