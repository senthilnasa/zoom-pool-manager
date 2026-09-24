<?php

namespace App\Http\Controllers;

use App\Domain\Settings\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LegalController extends Controller
{
    /**
     * Render or redirect to Privacy Policy.
     */
    public function privacyPolicy(Request $request): View|RedirectResponse
    {
        $type = (string) Setting::get('legal.privacy_policy_type', 'none');
        $url = (string) Setting::get('legal.privacy_policy_url', '');

        if ($type === 'url' && ! empty($url)) {
            return redirect()->away($url);
        }

        $content = Setting::get('legal.privacy_policy_content', '');
        $updatedAt = Setting::get('legal.privacy_policy_updated_at');

        return view('legal.page', [
            'documentTitle' => 'Privacy Policy',
            'type' => $type,
            'content' => $content,
            'updatedAt' => $updatedAt,
            'orgName' => Setting::get('org.name', config('app.name', 'Zoom Pool Manager')),
            'orgLogoUrl' => Setting::get('org.logo_url', ''),
            'orgSupportEmail' => Setting::get('org.support_email', 'support@zoompoolmanager.org'),
        ]);
    }

    /**
     * Render or redirect to Terms of Service.
     */
    public function termsOfService(Request $request): View|RedirectResponse
    {
        $type = (string) Setting::get('legal.terms_type', 'none');
        $url = (string) Setting::get('legal.terms_url', '');

        if ($type === 'url' && ! empty($url)) {
            return redirect()->away($url);
        }

        $content = Setting::get('legal.terms_content', '');
        $updatedAt = Setting::get('legal.terms_updated_at');

        return view('legal.page', [
            'documentTitle' => 'Terms of Service',
            'type' => $type,
            'content' => $content,
            'updatedAt' => $updatedAt,
            'orgName' => Setting::get('org.name', config('app.name', 'Zoom Pool Manager')),
            'orgLogoUrl' => Setting::get('org.logo_url', ''),
            'orgSupportEmail' => Setting::get('org.support_email', 'support@zoompoolmanager.org'),
        ]);
    }

    /**
     * Public JSON endpoint for SPA legal views and branding.
     */
    public function apiDocument(string $type): JsonResponse
    {
        if (! in_array($type, ['privacy', 'terms'])) {
            return response()->json(['message' => 'Document not found.'], 404);
        }

        $isPrivacy = $type === 'privacy';
        $docType = (string) Setting::get($isPrivacy ? 'legal.privacy_policy_type' : 'legal.terms_type', 'none');
        $url = (string) Setting::get($isPrivacy ? 'legal.privacy_policy_url' : 'legal.terms_url', '');
        $content = (string) Setting::get($isPrivacy ? 'legal.privacy_policy_content' : 'legal.terms_content', '');
        $updatedAt = (string) Setting::get($isPrivacy ? 'legal.privacy_policy_updated_at' : 'legal.terms_updated_at', '');

        return response()->json([
            'document' => $type,
            'title' => $isPrivacy ? 'Privacy Policy' : 'Terms of Service',
            'type' => $docType,
            'url' => $url,
            'content' => $content,
            'updated_at' => $updatedAt,
            'org_name' => Setting::get('org.name', config('app.name', 'Zoom Pool Manager')),
            'org_logo_url' => Setting::get('org.logo_url', ''),
            'org_support_email' => Setting::get('org.support_email', 'support@zoompoolmanager.org'),
        ]);
    }

    /**
     * Public JSON endpoint for general branding info.
     */
    public function branding(): JsonResponse
    {
        return response()->json([
            'org_name' => (string) Setting::get('org.name', config('app.name', 'Zoom Pool Manager')),
            'org_logo_url' => (string) Setting::get('org.logo_url', ''),
            'org_logo_dark_url' => (string) Setting::get('org.logo_dark_url', ''),
            'org_favicon_url' => (string) Setting::get('org.favicon_url', ''),
            'org_tagline' => (string) Setting::get('org.tagline', 'Zoom Pool Manager'),
            'org_primary_color' => (string) Setting::get('org.primary_color', '#0ea5e9'),
            'org_help_url' => (string) Setting::get('org.help_url', ''),
            'org_footer_text' => (string) Setting::get('org.footer_text', ''),
            'org_support_email' => (string) Setting::get('org.support_email', 'support@zoompoolmanager.org'),
            'org_website' => (string) Setting::get('org.website', url('/')),
            'privacy_policy' => [
                'type' => (string) Setting::get('legal.privacy_policy_type', 'none'),
                'url' => (string) Setting::get('legal.privacy_policy_url', ''),
            ],
            'terms' => [
                'type' => (string) Setting::get('legal.terms_type', 'none'),
                'url' => (string) Setting::get('legal.terms_url', ''),
            ],
        ]);
    }
}
