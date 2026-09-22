<?php

namespace App\Http\Middleware;

use App\Domain\Api\Models\ApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiScope
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');

        if (! $apiKey || ! $apiKey->hasScope($scope)) {
            return response()->json([
                'error' => 'Forbidden',
                'message' => "API key lacks required scope: {$scope}",
            ], 403);
        }

        return $next($request);
    }
}
