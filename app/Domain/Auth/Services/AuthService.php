<?php

namespace App\Domain\Auth\Services;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Auth\DTOs\UserIdentityDto;
use App\Domain\Auth\Models\IdentityProvider;
use App\Domain\Auth\Models\LoginAttempt;
use App\Domain\Auth\Models\UserIdentity;
use App\Domain\Settings\Models\Setting;
use App\Domain\Users\Models\Department;
use App\Domain\Users\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use PragmaRX\Google2FA\Google2FA;

class AuthService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Authenticate a user via email and password (local break-glass credentials).
     *
     * @return array{status: string, user?: User, error?: string}
     */
    public function validateLocalCredentials(
        string $email,
        string $password,
        string $ipAddress,
        ?string $userAgent,
        bool $remember = false
    ): array {
        $throttleKey = Str::lower($email).'|'.$ipAddress;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->recordLoginAttempt($email, $ipAddress, $userAgent, false, 'rate_limited');

            return [
                'status' => 'error',
                'error' => "Too many login attempts. Please try again in {$seconds} seconds.",
            ];
        }

        $user = User::where('email', $email)->first();

        if (! $user || ! $user->is_active || ! Hash::check($password, (string) $user->password)) {
            RateLimiter::hit($throttleKey, 300); // 5 minutes decay
            $this->recordLoginAttempt($email, $ipAddress, $userAgent, false, 'invalid_credentials');

            return [
                'status' => 'error',
                'error' => 'These credentials do not match our records.',
            ];
        }

        RateLimiter::clear($throttleKey);

        // Check if MFA is required: either explicitly enabled, or user is a super_admin
        $requiresMfa = $user->mfa_enabled || $user->hasRole('super_admin');

        if ($requiresMfa) {
            return [
                'status' => 'mfa_required',
                'user' => $user,
            ];
        }

        Auth::login($user, $remember);
        $user->last_login_at = now();
        $user->save();

        $this->recordLoginAttempt($email, $ipAddress, $userAgent, true, null);
        $this->auditService->log('auth.login', $user, null, null, $user, $ipAddress, $userAgent);

        return [
            'status' => 'authenticated',
            'user' => $user,
        ];
    }

    /**
     * Verify a submitted TOTP code or single-use recovery code.
     *
     * @return array{success: bool, error?: string}
     */
    public function verifyTotpChallenge(
        User $user,
        string $code,
        string $ipAddress,
        ?string $userAgent,
        bool $remember = false
    ): array {
        $throttleKey = 'totp|'.$user->id.'|'.$ipAddress;

        if (RateLimiter::tooManyAttempts($throttleKey, 5)) {
            $seconds = RateLimiter::availableIn($throttleKey);
            $this->recordLoginAttempt($user->email, $ipAddress, $userAgent, false, 'mfa_rate_limited');

            return [
                'success' => false,
                'error' => "Too many failed verification attempts. Please wait {$seconds} seconds.",
            ];
        }

        $code = trim($code);
        $cleanCode = str_replace('-', '', strtoupper($code));
        $isValid = false;
        $usedMethod = 'totp';

        // 1. Verify TOTP 6-digit code via Google2FA
        $secret = $user->mfaSecret?->secret;
        if ($secret && (new Google2FA)->verifyKey($secret, $code, 2)) {
            $isValid = true;
            $usedMethod = 'totp';
        }

        // 2. If TOTP failed, test against unused recovery codes
        if (! $isValid) {
            $recoveryCodes = $user->mfaRecoveryCodes()->whereNull('used_at')->get();
            foreach ($recoveryCodes as $recoveryCode) {
                if (Hash::check($cleanCode, $recoveryCode->code_hash)) {
                    $recoveryCode->used_at = now();
                    $recoveryCode->save();
                    $isValid = true;
                    $usedMethod = 'recovery_code';
                    break;
                }
            }
        }

        if (! $isValid) {
            RateLimiter::hit($throttleKey, 300);
            $this->recordLoginAttempt($user->email, $ipAddress, $userAgent, false, 'invalid_totp_code');
            $this->auditService->log('auth.mfa_failed', $user, null, null, null, $ipAddress, $userAgent);

            return [
                'success' => false,
                'error' => 'The provided two-factor authentication code is invalid.',
            ];
        }

        RateLimiter::clear($throttleKey);

        Auth::login($user, $remember);
        $user->last_login_at = now();
        $user->save();

        $this->recordLoginAttempt($user->email, $ipAddress, $userAgent, true, null);
        $this->auditService->log('auth.mfa_success', $user, null, ['method' => $usedMethod], $user, $ipAddress, $userAgent);

        return [
            'success' => true,
        ];
    }

    /**
     * Process an authenticated SSO user callback with domain checking and JIT provisioning.
     */
    public function handleSsoUser(
        IdentityProvider $provider,
        UserIdentityDto $dto,
        string $ipAddress,
        ?string $userAgent
    ): User {
        // 1. Check domain whitelist
        if (! empty($provider->allowed_domains)) {
            $emailDomain = strtolower((string) ltrim(strrchr($dto->email, '@') ?: '', '@'));
            $allowed = array_map('strtolower', $provider->allowed_domains);

            if (! in_array($emailDomain, $allowed, true)) {
                $this->recordLoginAttempt($dto->email, $ipAddress, $userAgent, false, 'domain_not_allowed');
                $this->auditService->log('auth.sso_denied', null, null, [
                    'email' => $dto->email,
                    'domain' => $emailDomain,
                    'provider' => $provider->name,
                ], null, $ipAddress, $userAgent);

                throw new AuthorizationException("Email domain [@{$emailDomain}] is not authorized to sign in with this provider.");
            }
        }

        // 2. Resolve or provision user
        /** @var User $user */
        $user = DB::transaction(function () use ($provider, $dto) {
            $identity = UserIdentity::where('identity_provider_id', $provider->id)
                ->where('external_id', $dto->externalId)
                ->first();

            if ($identity) {
                $user = $identity->user;
                $user->name = $dto->name ?: $user->name;
                $user->email = $dto->email;
                $user->save();
            } else {
                // Find existing user by email or create new user (JIT Provisioning)
                $user = User::where('email', $dto->email)->first();

                if (! $user) {
                    $user = User::create([
                        'name' => $dto->name ?: $dto->email,
                        'email' => $dto->email,
                        'password' => Hash::make(Str::random(32)),
                        'timezone' => Setting::get('org.timezone', 'Asia/Kolkata'),
                        'locale' => 'en',
                        'is_active' => true,
                        'mfa_enabled' => false,
                    ]);
                }

                $identity = UserIdentity::create([
                    'user_id' => $user->id,
                    'identity_provider_id' => $provider->id,
                    'external_id' => $dto->externalId,
                    'email' => $dto->email,
                    'last_authenticated_at' => now(),
                ]);
            }

            $identity->last_authenticated_at = now();
            $identity->save();

            // 3. Map Groups to Roles
            if (! empty($provider->role_mapping) && ! empty($dto->groups)) {
                $rolesToAssign = [];
                foreach ($provider->role_mapping as $groupName => $roleName) {
                    if (in_array($groupName, $dto->groups, true)) {
                        $rolesToAssign[] = (string) $roleName;
                    }
                }
                if (! empty($rolesToAssign)) {
                    $user->syncRoles(array_unique($rolesToAssign));
                }
            }

            // 4. Map Groups to Department
            if (! empty($provider->department_mapping) && ! empty($dto->groups)) {
                foreach ($provider->department_mapping as $groupName => $deptIdentifier) {
                    if (in_array($groupName, $dto->groups, true)) {
                        $dept = Department::where('name', $deptIdentifier)
                            ->orWhere('code', $deptIdentifier)
                            ->first();
                        if ($dept) {
                            $user->department_id = $dept->id;
                            $user->save();
                            break;
                        }
                    }
                }
            }

            return $user;
        });

        if (! $user->is_active) {
            $this->recordLoginAttempt($user->email, $ipAddress, $userAgent, false, 'account_deactivated');
            throw new AuthorizationException('This account has been deactivated. Please contact IT support.');
        }

        Auth::login($user, true);
        $user->last_login_at = now();
        $user->save();

        $this->recordLoginAttempt($user->email, $ipAddress, $userAgent, true, null);
        $this->auditService->log('auth.sso_login', $user, null, [
            'provider' => $provider->name,
            'driver' => $provider->driver,
        ], $user, $ipAddress, $userAgent);

        return $user;
    }

    /**
     * Terminate the authenticated user's session.
     */
    public function logout(User $user, ?string $ipAddress, ?string $userAgent): void
    {
        $this->auditService->log('auth.logout', $user, null, null, $user, $ipAddress, $userAgent);
        Auth::logout();
    }

    /**
     * Terminate all other sessions for the user.
     */
    public function logoutOtherDevices(User $user, string $password): bool
    {
        $loggedOut = (bool) Auth::logoutOtherDevices($password);

        if ($loggedOut) {
            $this->auditService->log('auth.logout_other_devices', $user, null, null, $user);
        }

        return $loggedOut;
    }

    /**
     * Force logout a specific user across all their active sessions.
     */
    public function forceLogoutUser(User $targetUser, ?User $actor = null): void
    {
        DB::table('sessions')->where('user_id', $targetUser->id)->delete();

        $this->auditService->log('admin.user_force_logout', $targetUser, null, [
            'target_email' => $targetUser->email,
        ], $actor);
    }

    /**
     * Record a login attempt entry.
     */
    protected function recordLoginAttempt(
        string $email,
        string $ipAddress,
        ?string $userAgent,
        bool $wasSuccessful,
        ?string $failureReason
    ): void {
        LoginAttempt::create([
            'email' => $email,
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'was_successful' => $wasSuccessful,
            'failure_reason' => $failureReason,
            'created_at' => now(),
        ]);
    }
}
