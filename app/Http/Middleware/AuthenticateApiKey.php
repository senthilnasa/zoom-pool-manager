<?php

namespace App\Http\Middleware;

use App\Domain\Api\Services\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiKey
{
    public function __construct(
        protected ApiKeyService $apiKeyService
    ) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header('Authorization');

        if (! $header || ! str_starts_with($header, 'Bearer ')) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Missing or malformed Authorization header. Expected Bearer token.',
            ], 401);
        }

        $token = substr($header, 7);
        $apiKey = $this->apiKeyService->authenticate($token);

        if (! $apiKey) {
            return response()->json([
                'error' => 'Unauthorized',
                'message' => 'Invalid, expired, or revoked API key.',
            ], 401);
        }

        // Rate limiting per API key
        $limiterKey = "api_key:{$apiKey->id}";
        $maxAttempts = $apiKey->rate_limit_per_minute ?: 60;

        if (RateLimiter::tooManyAttempts($limiterKey, $maxAttempts)) {
            $seconds = RateLimiter::availableIn($limiterKey);

            return response()->json([
                'error' => 'Too Many Requests',
                'message' => "Rate limit exceeded. Try again in {$seconds} seconds.",
            ], 429, [
                'Retry-After' => $seconds,
                'X-RateLimit-Limit' => $maxAttempts,
                'X-RateLimit-Remaining' => 0,
            ]);
        }

        RateLimiter::hit($limiterKey, 60);

        // Bind user to request & auth
        Auth::setUser($apiKey->user);
        $request->setUserResolver(fn () => $apiKey->user);
        $request->attributes->set('api_key', $apiKey);

        return $next($request);
    }
}
