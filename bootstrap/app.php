<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\HandleIdempotency;
use App\Http\Middleware\RequireApiScope;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            SecurityHeaders::class,
            EnsureInstalled::class,
        ]);

        $middleware->alias([
            'api.auth' => AuthenticateApiKey::class,
            'api.scope' => RequireApiScope::class,
            'idempotent' => HandleIdempotency::class,
        ]);

        $middleware->validateCsrfTokens(except: [
            'webhooks/zoom/*',
            'api/*',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

// Support moving .env outside the web root (SPEC Part C2)
$zpmPaths = file_exists(dirname(__DIR__).'/zpm-paths.php')
    ? require dirname(__DIR__).'/zpm-paths.php'
    : [];

if (! empty($zpmPaths['env_path'])) {
    $app->useEnvironmentPath($zpmPaths['env_path']);
}

if (! empty($zpmPaths['env_file'])) {
    $app->loadEnvironmentFrom($zpmPaths['env_file']);
}

return $app;
