<?php

namespace App\Domain\Api\Services;

use App\Domain\Api\Models\ApiKey;
use App\Domain\Audit\Services\AuditService;
use App\Domain\Users\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

class ApiKeyService
{
    public function __construct(
        protected AuditService $auditService
    ) {}

    /**
     * Generate a new scoped API key.
     *
     * @param  array<string>  $scopes
     * @return array{key: ApiKey, plainTextToken: string}
     */
    public function createKey(
        User $user,
        string $name,
        array $scopes,
        int $rateLimit = 60,
        ?Carbon $expiresAt = null,
        ?User $actor = null
    ): array {
        $entropy = Str::random(40);
        $plainTextToken = "zpm_live_{$entropy}";
        $prefix = substr($plainTextToken, 0, 15);
        $hash = hash('sha256', $plainTextToken);

        /** @var ApiKey $key */
        $key = ApiKey::create([
            'user_id' => $user->id,
            'name' => $name,
            'key_prefix' => $prefix,
            'key_hash' => $hash,
            'scopes' => $scopes,
            'rate_limit_per_minute' => $rateLimit,
            'expires_at' => $expiresAt,
            'last_used_at' => null,
            'revoked_at' => null,
        ]);

        $this->auditService->log(
            'api_key.created',
            $key,
            null,
            ['name' => $name, 'scopes' => $scopes, 'prefix' => $prefix],
            $actor ?? $user
        );

        return [
            'key' => $key,
            'plainTextToken' => $plainTextToken,
        ];
    }

    /**
     * Authenticate a bearer token string.
     */
    public function authenticate(string $plainTextToken): ?ApiKey
    {
        if (! str_starts_with($plainTextToken, 'zpm_live_')) {
            return null;
        }

        $hash = hash('sha256', $plainTextToken);

        /** @var ApiKey|null $key */
        $key = ApiKey::where('key_hash', $hash)->first();

        if (! $key || ! $key->isValid()) {
            return null;
        }

        // Record last used time silently without touching updated_at
        $key->timestamps = false;
        $key->last_used_at = now();
        $key->save();
        $key->timestamps = true;

        return $key;
    }

    /**
     * Revoke an API key.
     */
    public function revokeKey(ApiKey $key, ?User $actor = null): void
    {
        $key->update(['revoked_at' => now()]);

        $this->auditService->log(
            'api_key.revoked',
            $key,
            null,
            ['revoked_at' => now()->toIso8601String()],
            $actor
        );
    }
}
