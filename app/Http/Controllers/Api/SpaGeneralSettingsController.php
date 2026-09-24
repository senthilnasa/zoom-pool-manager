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
            // Institutional & Visual Identity Profile
            'org_name' => (string) Setting::get('org.name', config('app.name', 'Zoom Pool Manager')),
            'org_logo_url' => (string) Setting::get('org.logo_url', ''),
            'org_logo_dark_url' => (string) Setting::get('org.logo_dark_url', ''),
            'org_favicon_url' => (string) Setting::get('org.favicon_url', ''),
            'org_tagline' => (string) Setting::get('org.tagline', 'Zoom Pool Manager'),
            'org_primary_color' => (string) Setting::get('org.primary_color', '#0ea5e9'),
            'org_footer_text' => (string) Setting::get('org.footer_text', ''),
            'org_support_email' => (string) Setting::get('org.support_email', 'support@zoompoolmanager.org'),
            'org_website' => (string) Setting::get('org.website', url('/')),
            'org_help_url' => (string) Setting::get('org.help_url', ''),
            'org_login_heading' => (string) Setting::get('org.login_heading', ''),
            'org_login_subtext' => (string) Setting::get('org.login_subtext', ''),
            'org_timezone' => (string) Setting::get('org.timezone', 'Asia/Kolkata'),

            // Legal & Compliance Policies
            'privacy_policy_type' => (string) Setting::get('legal.privacy_policy_type', 'none'),
            'privacy_policy_url' => (string) Setting::get('legal.privacy_policy_url', ''),
            'privacy_policy_content' => (string) Setting::get('legal.privacy_policy_content', ''),
            'privacy_policy_updated_at' => (string) Setting::get('legal.privacy_policy_updated_at', ''),
            'terms_type' => (string) Setting::get('legal.terms_type', 'none'),
            'terms_url' => (string) Setting::get('legal.terms_url', ''),
            'terms_content' => (string) Setting::get('legal.terms_content', ''),
            'terms_updated_at' => (string) Setting::get('legal.terms_updated_at', ''),

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

            // Workflow & Approval Policies
            'require_meeting_approval' => (bool) Setting::get('org.require_meeting_approval', true),

            // NOC Wallboard API Token
            'noc_api_token' => (string) Setting::get('noc.api_token', 'noc_live_'.substr(hash('sha256', config('app.key', 'zpm_noc_default_secret')), 0, 32)),
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
     * Upload an institutional logo image file.
     */
    public function uploadLogo(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('Super Administrator') || $request->user()?->can('settings.manage') || $request->user()?->can('system.manage'), 403);

        $request->validate([
            'logo' => 'required|file|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
        ]);

        $file = $request->file('logo');
        $directory = public_path('uploads/branding');
        if (! file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = $file->getClientOriginalExtension() ?: 'png';
        $filename = 'logo_'.time().'.'.$extension;
        $file->move($directory, $filename);

        $logoUrl = '/uploads/branding/'.$filename;
        $oldLogo = Setting::get('org.logo_url');
        Setting::set('org.logo_url', $logoUrl);

        $this->auditService->log(
            event: 'settings.logo.uploaded',
            auditable: null,
            oldValues: ['org_logo_url' => $oldLogo],
            newValues: ['org_logo_url' => $logoUrl]
        );

        return response()->json([
            'success' => true,
            'message' => 'Institutional logo updated successfully.',
            'logo_url' => $logoUrl,
        ]);
    }

    /**
     * Remove custom institutional logo and revert to default.
     */
    public function deleteLogo(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('Super Administrator') || $request->user()?->can('settings.manage') || $request->user()?->can('system.manage'), 403);

        $currentLogo = (string) Setting::get('org.logo_url');
        if (! empty($currentLogo) && str_starts_with($currentLogo, '/uploads/branding/')) {
            $filePath = public_path(ltrim($currentLogo, '/'));
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        Setting::set('org.logo_url', '');

        $this->auditService->log(
            event: 'settings.logo.removed',
            auditable: null,
            oldValues: ['org_logo_url' => $currentLogo],
            newValues: ['org_logo_url' => '']
        );

        return response()->json([
            'success' => true,
            'message' => 'Institutional logo removed successfully.',
            'logo_url' => '',
        ]);
    }

    /**
     * Upload a dark mode institutional logo image file.
     */
    public function uploadDarkLogo(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('Super Administrator') || $request->user()?->can('settings.manage') || $request->user()?->can('system.manage'), 403);

        $request->validate([
            'logo_dark' => 'required|file|image|mimes:jpeg,png,jpg,gif,svg,webp|max:4096',
        ]);

        $file = $request->file('logo_dark');
        $directory = public_path('uploads/branding');
        if (! file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = $file->getClientOriginalExtension() ?: 'png';
        $filename = 'logo_dark_'.time().'.'.$extension;
        $file->move($directory, $filename);

        $logoDarkUrl = '/uploads/branding/'.$filename;
        $oldLogoDark = Setting::get('org.logo_dark_url');
        Setting::set('org.logo_dark_url', $logoDarkUrl);

        $this->auditService->log(
            event: 'settings.logo_dark.uploaded',
            auditable: null,
            oldValues: ['org_logo_dark_url' => $oldLogoDark],
            newValues: ['org_logo_dark_url' => $logoDarkUrl]
        );

        return response()->json([
            'success' => true,
            'message' => 'Dark mode logo updated successfully.',
            'logo_dark_url' => $logoDarkUrl,
        ]);
    }

    /**
     * Remove custom dark mode logo and revert to primary logo.
     */
    public function deleteDarkLogo(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('Super Administrator') || $request->user()?->can('settings.manage') || $request->user()?->can('system.manage'), 403);

        $currentLogoDark = (string) Setting::get('org.logo_dark_url');
        if (! empty($currentLogoDark) && str_starts_with($currentLogoDark, '/uploads/branding/')) {
            $filePath = public_path(ltrim($currentLogoDark, '/'));
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        Setting::set('org.logo_dark_url', '');

        $this->auditService->log(
            event: 'settings.logo_dark.removed',
            auditable: null,
            oldValues: ['org_logo_dark_url' => $currentLogoDark],
            newValues: ['org_logo_dark_url' => '']
        );

        return response()->json([
            'success' => true,
            'message' => 'Dark mode logo removed successfully.',
            'logo_dark_url' => '',
        ]);
    }

    /**
     * Upload custom institutional favicon file.
     */
    public function uploadFavicon(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('Super Administrator') || $request->user()?->can('settings.manage') || $request->user()?->can('system.manage'), 403);

        $request->validate([
            'favicon' => 'required|file|mimes:ico,png,svg,webp,jpg,jpeg|max:2048',
        ]);

        $file = $request->file('favicon');
        $directory = public_path('uploads/branding');
        if (! file_exists($directory)) {
            mkdir($directory, 0755, true);
        }

        $extension = $file->getClientOriginalExtension() ?: 'png';
        $filename = 'favicon_'.time().'.'.$extension;
        $file->move($directory, $filename);

        $faviconUrl = '/uploads/branding/'.$filename;
        $oldFavicon = Setting::get('org.favicon_url');
        Setting::set('org.favicon_url', $faviconUrl);

        $this->auditService->log(
            event: 'settings.favicon.uploaded',
            auditable: null,
            oldValues: ['org_favicon_url' => $oldFavicon],
            newValues: ['org_favicon_url' => $faviconUrl]
        );

        return response()->json([
            'success' => true,
            'message' => 'Favicon updated successfully.',
            'favicon_url' => $faviconUrl,
        ]);
    }

    /**
     * Remove custom favicon and revert to default.
     */
    public function deleteFavicon(Request $request): JsonResponse
    {
        abort_unless($request->user()?->hasRole('Super Administrator') || $request->user()?->can('settings.manage') || $request->user()?->can('system.manage'), 403);

        $currentFavicon = (string) Setting::get('org.favicon_url');
        if (! empty($currentFavicon) && str_starts_with($currentFavicon, '/uploads/branding/')) {
            $filePath = public_path(ltrim($currentFavicon, '/'));
            if (file_exists($filePath)) {
                @unlink($filePath);
            }
        }

        Setting::set('org.favicon_url', '');

        $this->auditService->log(
            event: 'settings.favicon.removed',
            auditable: null,
            oldValues: ['org_favicon_url' => $currentFavicon],
            newValues: ['org_favicon_url' => '']
        );

        return response()->json([
            'success' => true,
            'message' => 'Favicon removed successfully.',
            'favicon_url' => '',
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
            'org_logo_url' => 'nullable|string|max:1000',
            'org_logo_dark_url' => 'nullable|string|max:1000',
            'org_favicon_url' => 'nullable|string|max:1000',
            'org_tagline' => 'nullable|string|max:255',
            'org_primary_color' => ['nullable', 'string', 'regex:/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/'],
            'org_footer_text' => 'nullable|string|max:255',
            'org_support_email' => 'required|email|max:150',
            'org_website' => 'nullable|url|max:200',
            'org_help_url' => 'nullable|string|max:500',
            'org_login_heading' => 'nullable|string|max:255',
            'org_login_subtext' => 'nullable|string|max:500',
            'org_timezone' => 'required|string|timezone',

            'privacy_policy_type' => 'nullable|string|in:none,url,custom',
            'privacy_policy_url' => 'nullable|string|max:500',
            'privacy_policy_content' => 'nullable|string',
            'terms_type' => 'nullable|string|in:none,url,custom',
            'terms_url' => 'nullable|string|max:500',
            'terms_content' => 'nullable|string',

            'org_min_buffer_minutes' => 'required|integer|min:0|max:120',
            'org_default_buffer_minutes' => 'required|integer|min:0|max:120',
            'org_min_notice_hours' => 'required|integer|min:0|max:168',
            'org_max_advance_days' => 'required|integer|min:1|max:365',
            'org_max_duration_minutes' => 'required|integer|min:15|max:1440',
            'host_lead_minutes' => 'required|integer|min:0|max:120',

            'org_ai_companion_policy' => 'required|string|in:ALLOWED,RESTRICTED,DISABLED',
            'org_default_recording_mode' => 'required|string|in:none,cloud,local,mandatory_cloud',
            'require_meeting_approval' => 'nullable|boolean',
            'noc_api_token' => 'nullable|string|max:100',
        ]);

        $oldSettings = [
            'org_name' => Setting::get('org.name'),
            'org_logo_url' => Setting::get('org.logo_url'),
            'org_logo_dark_url' => Setting::get('org.logo_dark_url'),
            'org_favicon_url' => Setting::get('org.favicon_url'),
            'org_tagline' => Setting::get('org.tagline'),
            'org_primary_color' => Setting::get('org.primary_color'),
            'org_timezone' => Setting::get('org.timezone'),
            'org_min_buffer_minutes' => Setting::get('org.min_buffer_minutes'),
        ];

        // Persist institutional & branding settings
        Setting::set('org.name', $validated['org_name']);
        if (array_key_exists('org_logo_url', $validated)) {
            Setting::set('org.logo_url', $validated['org_logo_url'] ?? '');
        }
        if (array_key_exists('org_logo_dark_url', $validated)) {
            Setting::set('org.logo_dark_url', $validated['org_logo_dark_url'] ?? '');
        }
        if (array_key_exists('org_favicon_url', $validated)) {
            Setting::set('org.favicon_url', $validated['org_favicon_url'] ?? '');
        }
        if (array_key_exists('org_tagline', $validated)) {
            Setting::set('org.tagline', $validated['org_tagline'] ?? '');
        }
        if (array_key_exists('org_primary_color', $validated)) {
            Setting::set('org.primary_color', $validated['org_primary_color'] ?? '#0ea5e9');
        }
        if (array_key_exists('org_footer_text', $validated)) {
            Setting::set('org.footer_text', $validated['org_footer_text'] ?? '');
        }
        if (array_key_exists('org_help_url', $validated)) {
            Setting::set('org.help_url', $validated['org_help_url'] ?? '');
        }
        if (array_key_exists('org_login_heading', $validated)) {
            Setting::set('org.login_heading', $validated['org_login_heading'] ?? '');
        }
        if (array_key_exists('org_login_subtext', $validated)) {
            Setting::set('org.login_subtext', $validated['org_login_subtext'] ?? '');
        }
        Setting::set('org.support_email', $validated['org_support_email']);
        Setting::set('org.website', $validated['org_website'] ?? url('/'));
        Setting::set('org.timezone', $validated['org_timezone']);

        // Persist Legal & Compliance Policies
        if (isset($validated['privacy_policy_type'])) {
            Setting::set('legal.privacy_policy_type', $validated['privacy_policy_type']);
            Setting::set('legal.privacy_policy_url', $validated['privacy_policy_url'] ?? '');
            Setting::set('legal.privacy_policy_content', $validated['privacy_policy_content'] ?? '');
            Setting::set('legal.privacy_policy_updated_at', now()->toIso8601String());
        }

        if (isset($validated['terms_type'])) {
            Setting::set('legal.terms_type', $validated['terms_type']);
            Setting::set('legal.terms_url', $validated['terms_url'] ?? '');
            Setting::set('legal.terms_content', $validated['terms_content'] ?? '');
            Setting::set('legal.terms_updated_at', now()->toIso8601String());
        }

        Setting::set('org.min_buffer_minutes', $validated['org_min_buffer_minutes']);
        Setting::set('org.default_buffer_minutes', $validated['org_default_buffer_minutes']);
        Setting::set('org.min_notice_hours', $validated['org_min_notice_hours']);
        Setting::set('org.max_advance_days', $validated['org_max_advance_days']);
        Setting::set('org.max_duration_minutes', $validated['org_max_duration_minutes']);
        Setting::set('host.lead_minutes', $validated['host_lead_minutes']);

        Setting::set('org.ai_companion_policy', $validated['org_ai_companion_policy']);
        Setting::set('org.default_recording_mode', $validated['org_default_recording_mode']);

        if (array_key_exists('require_meeting_approval', $validated)) {
            Setting::set('org.require_meeting_approval', (bool) $validated['require_meeting_approval']);
        }
        if (! empty($validated['noc_api_token'])) {
            Setting::set('noc.api_token', trim((string) $validated['noc_api_token']));
        }

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
