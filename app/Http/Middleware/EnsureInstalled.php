<?php

namespace App\Http\Middleware;

use App\Domain\Settings\Models\Setting;
use Closure;
use Illuminate\Http\Request;
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

        if ($request->is('admin/health') || $request->is('up')) {
            return $next($request);
        }

        if (! $isInstalled && ! $isInstallerRoute) {
            return redirect()->route('installer.welcome');
        }

        if ($isInstalled && $isInstallerRoute) {
            abort(403, 'Zoom Pool Manager is already installed. To re-run the installer, execute: php artisan zpm:installer:unlock');
        }

        return $next($request);
    }

    /**
     * Determine if the application has been installed.
     * Checks both the lock file and database flag per SPEC Part H1.
     */
    public function isInstalled(): bool
    {
        if (static::$bypassForTesting !== null) {
            return static::$bypassForTesting;
        }

        if (file_exists(storage_path(self::LOCK_FILE))) {
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
        } catch (\Throwable $e) {
            // DB not reachable or not migrated yet during initial setup
        }

        return false;
    }
}
