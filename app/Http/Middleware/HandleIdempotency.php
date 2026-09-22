<?php

namespace App\Http\Middleware;

use App\Domain\Api\Models\ApiKey;
use App\Domain\Api\Models\IdempotencyRecord;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class HandleIdempotency
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $idempotencyKey = $request->header('Idempotency-Key');

        if (! $idempotencyKey || ! in_array($request->method(), ['POST', 'PUT', 'PATCH', 'DELETE'], true)) {
            return $next($request);
        }

        /** @var ApiKey|null $apiKey */
        $apiKey = $request->attributes->get('api_key');
        $scopedKey = ($apiKey ? "key_{$apiKey->id}:" : 'anon:').$idempotencyKey;
        $requestHash = hash('sha256', (string) $request->getContent());

        /** @var IdempotencyRecord|null $record */
        $record = IdempotencyRecord::where('key', $scopedKey)->first();

        if ($record) {
            if ($record->request_hash !== $requestHash) {
                return response()->json([
                    'error' => 'Unprocessable Entity',
                    'message' => 'Idempotency key reused with different request payload.',
                ], 422);
            }

            return response($record->response_body, $record->response_status, [
                'Content-Type' => 'application/json',
                'X-Cache' => 'HIT-IDEMPOTENT',
            ]);
        }

        $response = $next($request);

        // Only store successful or 4xx responses (skip server 5xx errors to permit retrying)
        if ($response->getStatusCode() < 500) {
            IdempotencyRecord::create([
                'key' => $scopedKey,
                'user_id' => $apiKey?->user_id,
                'api_key_id' => $apiKey?->id,
                'route' => (string) $request->path(),
                'request_hash' => $requestHash,
                'response_status' => $response->getStatusCode(),
                'response_headers' => [],
                'response_body' => $response->getContent() ?: '',
            ]);
        }

        return $response;
    }
}
