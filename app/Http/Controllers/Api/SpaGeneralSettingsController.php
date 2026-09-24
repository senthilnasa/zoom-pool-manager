<?php

namespace App\Http\Controllers\Api;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Settings\Models\Setting;
use App\Http\Controllers\Controller;
use DateTimeZone;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SpaGeneralSettingsController extends Controller
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Get all general system, institutional, and global policy settings.
     */
    public function show(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('Super Administrator') || $request->user()?->can('settings.manage') || $request->user()?->can('system.manage'), 403);
        $settings = [
            // Institutional Profile
            'org_name' => (string) Setting::get('org.name', config('app.name', 'Zoom Pool Manager')),
            'org_support_email' => (string) Setting::get('org.support_email', 'support@zoompoolmanager.org'),
            'org_website' => (string) Setting::get('org.website', url('/')),
            'org_timezone' => (string) Setting::get('org.timezone', 'Asia/Kolkata'),

            // Global Scheduling Constraints
            'org_min_buffer_minutes' => (int) Setting::get('org.min_buffer_minutes', 10),
            'org_default_buffer_minutes' => (int) Setting::get('org.default_buffer_minutes', 10),
            'org_min_notice_hours' => (int) Setting::get('org.min_notice_hours', 2),
            'org_max_advance_days' => (int) Setting::get('org.max_advance_days', 90),
            'org_max_duration_minutes' => (int) Setting::get('org.max_duration_minutes', 480),
            'host_lead_minutes' => (int) Setting::get('host.lead_minutes', 15),

            // Recording & Governance Policies
            'org_ai_companion_policy' => (string) Setting::get('org.ai_companion_policy', 'ALLOWED'),
            'org_default_recording_mode' => (string) Setting::get('org.default_recording_mode', 'none'),
        ];

        // Curated list of timezones
        $timezones = DateTimeZone::listIdentifiers(DateTimeZone::ALL);

        return response()->json([
            'settings' => $settings,
            'timezones' => $timezones,
            'recording_modes' => [
                ['value' => 'none', 'label' => 'No Automatic Recording (Host Discretion)'],
                ['value' => 'cloud', 'label' => 'Automatic Cloud Recording (Standard)'],
                ['value' => 'local', 'label' => 'Automatic Local Recording (Device)'],
                ['value' => 'mandatory_cloud', 'label' => 'Mandatory Cloud Recording (Institutional Lock)'],
            ],
            'ai_companion_policies' => [
                ['value' => 'ALLOWED', 'label' => 'Allowed (Host Opt-In / Discretion)'],
                ['value' => 'RESTRICTED', 'label' => 'Restricted (Requires Approval Chain)'],
                ['value' => 'DISABLED', 'label' => 'Disabled Institution-Wide (Strict Privacy)'],
            ],
        ]);
    }

    /**
     * Update general settings.
     */
    public function update(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('Super Administrator') || $request->user()?->can('settings.manage') || $request->user()?->can('system.manage'), 403);

        $validated = $request->validate([
            'org_name' => 'required|string|max:150',
            'org_support_email' => 'required|email|max:150',
            'org_website' => 'nullable|url|max:200',
            'org_timezone' => 'required|string|timezone',

            'org_min_buffer_minutes' => 'required|integer|min:0|max:120',
            'org_default_buffer_minutes' => 'required|integer|min:0|max:120',
            'org_min_notice_hours' => 'required|integer|min:0|max:168',
            'org_max_advance_days' => 'required|integer|min:1|max:365',
            'org_max_duration_minutes' => 'required|integer|min:15|max:1440',
            'host_lead_minutes' => 'required|integer|min:0|max:120',

            'org_ai_companion_policy' => 'required|string|in:ALLOWED,RESTRICTED,DISABLED',
            'org_default_recording_mode' => 'required|string|in:none,cloud,local,mandatory_cloud',
        ]);

        $oldSettings = [
            'org_name' => Setting::get('org.name'),
            'org_timezone' => Setting::get('org.timezone'),
            'org_min_buffer_minutes' => Setting::get('org.min_buffer_minutes'),
        ];

        // Persist all settings
        Setting::set('org.name', $validated['org_name']);
        Setting::set('org.support_email', $validated['org_support_email']);
        Setting::set('org.website', $validated['org_website'] ?? url('/'));
        Setting::set('org.timezone', $validated['org_timezone']);

        Setting::set('org.min_buffer_minutes', $validated['org_min_buffer_minutes']);
        Setting::set('org.default_buffer_minutes', $validated['org_default_buffer_minutes']);
        Setting::set('org.min_notice_hours', $validated['org_min_notice_hours']);
        Setting::set('org.max_advance_days', $validated['org_max_advance_days']);
        Setting::set('org.max_duration_minutes', $validated['org_max_duration_minutes']);
        Setting::set('host.lead_minutes', $validated['host_lead_minutes']);

        Setting::set('org.ai_companion_policy', $validated['org_ai_companion_policy']);
        Setting::set('org.default_recording_mode', $validated['org_default_recording_mode']);

        $this->auditService->log(
            event: 'settings.general.updated',
            auditable: null,
            oldValues: $oldSettings,
            newValues: $validated
        );

        return response()->json([
            'success' => true,
            'message' => 'General institutional settings updated successfully.',
            'settings' => $validated,
        ]);
    }
}
