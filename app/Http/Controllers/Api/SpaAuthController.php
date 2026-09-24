<?php

namespace App\Http\Controllers\Api;

use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SpaAuthController extends Controller
{
    /**
     * Get the authenticated user's profile and permissions.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = Auth::user();

        if (! $user) {
            return response()->json(['authenticated' => false], 401);
        }

        $user->load(['department', 'roles', 'permissions']);

        return response()->json([
            'authenticated' => true,
            'user' => [
                'id' => $user->id,
                'public_id' => $user->public_id,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url ?? null,
                'theme' => $user->theme ?? 'system',
                'department' => $user->department ? [
                    'id' => $user->department->id,
                    'name' => $user->department->name,
                ] : null,
                'roles' => $user->roles->pluck('name'),
                'is_admin' => $user->hasRole('Super Administrator') || $user->hasRole('Administrator'),
                'permissions' => $user->getAllPermissions()->pluck('name'),
            ],
            'branding' => [
                'org_name' => (string) Setting::get('org.name', config('app.name', 'Zoom Pool Manager')),
                'org_logo_url' => (string) Setting::get('org.logo_url', ''),
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
            ],
            'demo_mode' => (bool) config('app.demo', false),
            'app_version' => (string) config('zpm.version', '1.0.0'),
        ]);
    }

    /**
     * Update user theme preference (system, dark, light).
     */
    public function updateTheme(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'theme' => ['required', 'string', 'in:system,dark,light'],
        ]);

        /** @var User $user */
        $user = Auth::user();
        $user->update(['theme' => $validated['theme']]);

        return response()->json([
            'success' => true,
            'theme' => $user->theme,
        ]);
    }

    /**
     * Refresh CSRF token and return current session authentication status.
     * Keeps the active session alive and ensures SPA clients never encounter stale tokens.
     */
    public function csrfToken(Request $request): JsonResponse
    {
        // Touching the session updates last_activity timestamp in session store
        $request->session()->put('_zpm_last_ping', time());

        /** @var User|null $user */
        $user = Auth::user();

        return response()->json([
            'csrf_token' => csrf_token(),
            'authenticated' => Auth::check(),
            'user' => $user ? [
                'id' => $user->id,
                'public_id' => $user->public_id,
                'name' => $user->name,
                'email' => $user->email,
            ] : null,
        ]);
    }
}
