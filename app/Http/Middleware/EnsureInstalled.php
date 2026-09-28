<?php

namespace App\Http\Middleware;

use App\Domain\Settings\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureInstalled
{
    /**
     * Path to the installer lock file.
     */
    public const LOCK_FILE = 'installed.lock';

    /**
     * Testing override bypass.
     */
    public static ?bool $bypassForTesting = null;

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $isInstalled = $this->isInstalled();
        $isInstallerRoute = $request->is('installer*');

        if ($request->is('admin/health') || $request->is('up') || $request->is('webhooks/*') || $request->is('api/webhooks/*')) {
            return $next($request);
        }

        if (! $isInstalled && ! $isInstallerRoute) {
            return redirect()->route('installer.welcome');
        }

        if ($isInstalled && $isInstallerRoute) {
            return redirect()->route('login');
        }

        return $next($request);
    }

    /**
     * Determine if the application has been installed.
     * Checks both the lock file, environment, and database state per SPEC Part H1.
     */
    public function isInstalled(): bool
    {
        if (static::$bypassForTesting !== null) {
            return static::$bypassForTesting;
        }

        if (file_exists(storage_path(self::LOCK_FILE))) {
            return true;
        }

        if ((bool) config('app.installed', false)) {
            return true;
        }

        try {
            if (Schema::hasTable('system_settings')) {
                $installedAt = Setting::get('installed_at');
                if (! empty($installedAt)) {
                    // Self-heal: recreate lock file on disk if missing
                    @file_put_contents(storage_path(self::LOCK_FILE), json_encode([
                        'installed_at' => $installedAt,
                        'healed_at' => now()->toIso8601String(),
                    ], JSON_PRETTY_PRINT));

                    return true;
                }
            }

            // If .env is already configured with an existing user in database, consider installed
            if (Schema::hasTable('users') && DB::table('users')->exists()) {
                @file_put_contents(storage_path(self::LOCK_FILE), json_encode([
                    'installed_at' => now()->toIso8601String(),
                    'auto_detected' => true,
                ], JSON_PRETTY_PRINT));

                return true;
            }
        } catch (\Throwable $e) {
            // DB not reachable or not migrated yet during initial setup
        }

        return false;
    }
}
