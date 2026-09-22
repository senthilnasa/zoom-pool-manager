<?php

namespace App\Domain\Scheduling\Services;

use App\Domain\Scheduling\DTOs\ResolvedPolicyDto;
use App\Domain\Scheduling\Models\BookingPolicy;
use App\Domain\Scheduling\Models\MeetingTemplate;
use App\Domain\Scheduling\Models\SecurityProfile;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;

class EffectivePolicyResolver
{
    /**
     * Resolve the effective scheduling and security policy.
     * Hierarchy: Org Policy -> Security Profile -> Dept Policy -> Role Policy -> Template -> Request
     *
     * @param  array<string, mixed>  $requestInput
     */
    public function resolve(
        ?User $user = null,
        ?Department $department = null,
        ?SecurityProfile $securityProfile = null,
        ?MeetingTemplate $template = null,
        array $requestInput = []
    ): ResolvedPolicyDto {
        // 1. Organization Level Defaults
        $orgMinBuffer = (int) Setting::get('org.min_buffer_minutes', 10);
        $orgDefaultBuffer = (int) Setting::get('org.default_buffer_minutes', 10);
        $orgMinNoticeHours = (int) Setting::get('org.min_notice_hours', 2);
        $orgMaxAdvanceDays = (int) Setting::get('org.max_advance_days', 90);
        $orgMaxDurationMinutes = (int) Setting::get('org.max_duration_minutes', 480);
        $orgAiCompanion = (string) Setting::get('org.ai_companion_policy', 'ALLOWED');
        $orgRecordingMode = (string) Setting::get('org.default_recording_mode', 'none');

        $effectiveMinBuffer = $orgMinBuffer;
        $effectiveBuffer = max($orgDefaultBuffer, $orgMinBuffer);
        $effectiveMinNoticeHours = $orgMinNoticeHours;
        $effectiveMaxAdvanceDays = $orgMaxAdvanceDays;
        $effectiveMaxDurationMinutes = $orgMaxDurationMinutes;
        $effectiveAiCompanion = $orgAiCompanion;
        $effectiveRecordingMode = $orgRecordingMode;
        $securitySettings = [];

        // 2. Security Profile Level (Takes precedence on security controls and AI companion)
        if ($securityProfile) {
            $profileSettings = (array) $securityProfile->settings;
            $securitySettings = array_merge($securitySettings, $profileSettings);

            if (! empty($profileSettings['ai_companion'])) {
                // If profile enforces DISABLED or REQUIRED, higher precedence locks it
                $effectiveAiCompanion = (string) $profileSettings['ai_companion'];
            }
        }

        // 3. Department Policy Level (Can only restrict further)
        $deptId = $department ? $department->id : ($user ? $user->department_id : null);
        if ($deptId) {
            $deptPolicy = BookingPolicy::where('department_id', $deptId)->where('is_active', true)->first();
            if ($deptPolicy) {
                // Department can demand more notice (higher hours)
                $effectiveMinNoticeHours = max($effectiveMinNoticeHours, $deptPolicy->min_notice_hours);
                // Department can restrict advance booking (fewer days)
                $effectiveMaxAdvanceDays = min($effectiveMaxAdvanceDays, $deptPolicy->max_advance_days);
                // Department can demand larger buffer (higher minutes)
                $effectiveMinBuffer = max($effectiveMinBuffer, $deptPolicy->min_buffer_minutes);
                $effectiveBuffer = max($effectiveBuffer, $deptPolicy->default_buffer_minutes);
                // Department can restrict max duration (shorter minutes)
                $effectiveMaxDurationMinutes = min($effectiveMaxDurationMinutes, $deptPolicy->max_duration_minutes);
            }
        }

        // 4. Role Policy Level (Privileged roles may have overrides)
        if ($user && $user->hasRole(['super_admin', 'it_admin', 'meeting_admin'])) {
            // Administrators can book on zero notice if needed
            $effectiveMinNoticeHours = 0;
            $effectiveMaxAdvanceDays = 365;
        }

        // 5. Template Level
        if ($template) {
            if ($template->max_duration_minutes > 0) {
                $effectiveMaxDurationMinutes = min($effectiveMaxDurationMinutes, $template->max_duration_minutes);
            }
            if (! empty($template->ai_companion_policy) && $effectiveAiCompanion !== 'DISABLED') {
                $effectiveAiCompanion = $template->ai_companion_policy;
            }
            if (! empty($template->recording_mode)) {
                $effectiveRecordingMode = $template->recording_mode;
            }
        }

        // 6. User Request Level (Can only restrict or pick within allowed bounds)
        if (! empty($requestInput['buffer_minutes'])) {
            $requestedBuffer = (int) $requestInput['buffer_minutes'];
            // User cannot go below effective minimum buffer
            $effectiveBuffer = max($effectiveMinBuffer, $requestedBuffer);
        }

        if (! empty($requestInput['recording_mode'])) {
            $requestedRecording = (string) $requestInput['recording_mode'];
            // If security profile explicitly disabled recording, user cannot enable
            if (! isset($securitySettings['allow_recording']) || $securitySettings['allow_recording'] !== false) {
                $effectiveRecordingMode = $requestedRecording;
            }
        }

        if (! empty($requestInput['ai_companion_policy']) && $effectiveAiCompanion === 'ALLOWED') {
            $effectiveAiCompanion = (string) $requestInput['ai_companion_policy'];
        }

        return new ResolvedPolicyDto(
            bufferMinutes: $effectiveBuffer,
            minNoticeHours: $effectiveMinNoticeHours,
            maxAdvanceDays: $effectiveMaxAdvanceDays,
            maxDurationMinutes: $effectiveMaxDurationMinutes,
            aiCompanionPolicy: $effectiveAiCompanion,
            recordingMode: $effectiveRecordingMode,
            securitySettings: $securitySettings,
        );
    }
}
