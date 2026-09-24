<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Security headers
        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');

        // Content Security Policy (CSP)
        // Allows Alpine.js, Tailwind CSS, Stoplight Elements (Scramble API Docs) and required assets
        $csp = "default-src 'self'; "
            ."script-src 'self' 'unsafe-inline' 'unsafe-eval' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://unpkg.com; "
            ."script-src-elem 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://cdn.jsdelivr.net https://unpkg.com; "
            ."style-src 'self' 'unsafe-inline' https://cdn.tailwindcss.com https://fonts.googleapis.com https://unpkg.com; "
            ."img-src 'self' data: https:; "
            ."font-src 'self' data: https://fonts.gstatic.com; "
            ."connect-src 'self' https://cdn.tailwindcss.com https://unpkg.com; "
            ."frame-ancestors 'self'; "
            ."form-action 'self' https: http:;";

        $response->headers->set('Content-Security-Policy', $csp);

        // Strict-Transport-Security (HSTS) if connection is HTTPS
        if ($request->isSecure()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
