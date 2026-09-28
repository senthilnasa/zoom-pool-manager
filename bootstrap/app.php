<?php

use App\Http\Middleware\AuthenticateApiKey;
use App\Http\Middleware\EnsureInstalled;
use App\Http\Middleware\HandleIdempotency;
use App\Http\Middleware\RequireApiScope;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

// Support moving .env outside the web root (SPEC Part C2)
$zpmPaths = file_exists(dirname(__DIR__).'/zpm-paths.php')
    ? require dirname(__DIR__).'/zpm-paths.php'
    : [];

$baseDir = dirname(__DIR__);
$envPath = ! empty($zpmPaths['env_path'])
    ? rtrim($zpmPaths['env_path'], '/\\').'/'.(! empty($zpmPaths['env_file']) ? $zpmPaths['env_file'] : '.env')
    : $baseDir.'/.env';
$examplePath = $baseDir.'/.env.example';

// Zero-CLI shared hosting: auto-initialize .env and APP_KEY if missing
if (! file_exists($envPath) && file_exists($examplePath)) {
    @copy($examplePath, $envPath);
}

if (file_exists($envPath)) {
    $envContent = (string) @file_get_contents($envPath);
    if (! preg_match('/^APP_KEY=base64:[A-Za-z0-9+\/]{43}=/m', $envContent)) {
        try {
            $generatedKey = 'base64:'.base64_encode(random_bytes(32));
            if (preg_match('/^APP_KEY=.*/m', $envContent)) {
                $envContent = preg_replace('/^APP_KEY=.*/m', "APP_KEY={$generatedKey}", $envContent);
            } else {
                $envContent = "APP_KEY={$generatedKey}\n".$envContent;
            }
            @file_put_contents($envPath, $envContent);
            putenv("APP_KEY={$generatedKey}");
            $_ENV['APP_KEY'] = $generatedKey;
            $_SERVER['APP_KEY'] = $generatedKey;
        } catch (Throwable) {
        }
    }
}

// Force file sessions when uninstalled so database is never queried before setup wizard runs
if (! file_exists($baseDir.'/storage/installed.lock')) {
    putenv('SESSION_DRIVER=file');
    $_ENV['SESSION_DRIVER'] = 'file';
    $_SERVER['SESSION_DRIVER'] = 'file';
}

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->trustProxies(at: '*');

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
            'webhooks/zoom*',
            'api/webhooks/zoom*',
            'api/*',
            'installer/database/test',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

if (! empty($zpmPaths['env_path'])) {
    $app->useEnvironmentPath($zpmPaths['env_path']);
}

if (! empty($zpmPaths['env_file'])) {
    $app->loadEnvironmentFrom($zpmPaths['env_file']);
}

return $app;
