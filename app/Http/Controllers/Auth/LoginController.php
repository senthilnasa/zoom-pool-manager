<?php

namespace App\Http\Controllers\Auth;

use App\Domain\Auth\Models\IdentityProvider;
use App\Domain\Auth\Services\AuthService;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\User;
use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class LoginController extends Controller
{
    public function __construct(
        protected AuthService $authService
    ) {}

    /**
     * Show the main login page (presents SSO providers and optional local login).
     */
    public function showLoginForm(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        $ssoOnly = (bool) Setting::get('auth.sso_only', false);
        $providers = IdentityProvider::where('enabled', true)->get();

        return view('auth.login', [
            'providers' => $providers,
            'ssoOnly' => $ssoOnly,
        ]);
    }

    /**
     * Show the local break-glass login page.
     */
    public function showLocalLoginForm(Request $request): View|RedirectResponse
    {
        if ($request->user()) {
            return redirect()->route('dashboard');
        }

        return view('auth.login-local');
    }

    /**
     * Handle local credential authentication.
     */
    public function login(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'string', 'email'],
            'password' => ['required', 'string'],
        ]);

        $remember = $request->boolean('remember');

        $result = $this->authService->validateLocalCredentials(
            email: $validated['email'],
            password: $validated['password'],
            ipAddress: $request->ip() ?? '127.0.0.1',
            userAgent: $request->userAgent(),
            remember: $remember
        );

        if ($result['status'] === 'error') {
            return back()->withInput($request->only('email'))->withErrors([
                'email' => $result['error'] ?? 'Authentication failed.',
            ]);
        }

        if ($result['status'] === 'mfa_required' && isset($result['user'])) {
            $request->session()->put('auth.mfa_user_id', $result['user']->id);
            $request->session()->put('auth.mfa_remember', $remember);

            return redirect()->route('auth.totp');
        }

        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Show the two-factor authentication TOTP / Recovery Code challenge.
     */
    public function showTotpForm(Request $request): View|RedirectResponse
    {
        if (! $request->session()->has('auth.mfa_user_id')) {
            return redirect()->route('login');
        }

        return view('auth.login-totp');
    }

    /**
     * Verify the two-factor authentication challenge.
     */
    public function verifyTotp(Request $request): RedirectResponse
    {
        $userId = $request->session()->get('auth.mfa_user_id');
        if (! $userId) {
            return redirect()->route('login');
        }

        $code = $request->input('code')
            ?? $request->input('totp_code')
            ?? $request->input('recovery_code');

        if (is_array($code)) {
            $code = collect($code)->map(fn ($v) => is_string($v) ? trim($v) : $v)->filter()->last();
        }

        // If code is empty, handle duplicate parameter in raw payload (e.g. code=123456&code=) or JSON
        if (empty($code)) {
            $rawContent = (string) $request->getContent();
            if ($request->isJson() || str_starts_with($rawContent, '{')) {
                $jsonData = json_decode($rawContent, true);
                $code = $jsonData['code'] ?? $jsonData['totp_code'] ?? $jsonData['recovery_code'] ?? null;
            } else {
                $pairs = explode('&', $rawContent);
                foreach ($pairs as $pair) {
                    $parts = explode('=', $pair, 2);
                    $key = urldecode($parts[0] ?? '');
                    $val = urldecode($parts[1] ?? '');
                    if (in_array($key, ['code', 'totp_code', 'recovery_code'], true) && trim($val) !== '') {
                        $code = trim($val);
                        break;
                    }
                }
            }
        }

        $code = is_string($code) || is_numeric($code) ? trim((string) $code) : '';

        $request->merge(['code' => $code]);

        $request->validate([
            'code' => ['required', 'string'],
        ]);

        $user = User::find($userId);
        if (! $user) {
            $request->session()->forget('auth.mfa_user_id');

            return redirect()->route('login')->withErrors(['email' => 'User session expired.']);
        }

        $remember = (bool) $request->session()->pull('auth.mfa_remember', false);

        $result = $this->authService->verifyTotpChallenge(
            user: $user,
            code: (string) $request->input('code'),
            ipAddress: $request->ip() ?? '127.0.0.1',
            userAgent: $request->userAgent(),
            remember: $remember
        );

        if (! $result['success']) {
            return back()->withErrors([
                'code' => $result['error'] ?? 'The provided authentication code is invalid.',
            ]);
        }

        $request->session()->forget('auth.mfa_user_id');
        $request->session()->regenerate();

        return redirect()->intended(route('dashboard'));
    }

    /**
     * Log the user out of the application.
     */
    public function logout(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user) {
            $this->authService->logout($user, $request->ip(), $request->userAgent());
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    /**
     * Log out all other active sessions for this account.
     */
    public function logoutOtherDevices(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'string'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $success = $this->authService->logoutOtherDevices($user, (string) $request->input('password'));

        if (! $success) {
            return back()->withErrors(['password' => 'The provided password was incorrect.']);
        }

        return back()->with('status', 'Successfully logged out all other active browser sessions.');
    }
}
