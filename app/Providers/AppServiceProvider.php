<?php

namespace App\Providers;

use App\Domain\HostControl\Contracts\MeetingHostProviderInterface;
use App\Domain\HostControl\Services\FakeMeetingHostProvider;
use App\Domain\HostControl\Services\ZoomMeetingHostProvider;
use App\Domain\Users\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(
            MeetingHostProviderInterface::class,
            function () {
                if (config('app.demo')) {
                    return new FakeMeetingHostProvider;
                }

                return $this->app->make(ZoomMeetingHostProvider::class);
            }
        );
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure standard max key length for utf8mb4 on older MySQL/MariaDB
        Schema::defaultStringLength(191);

        Gate::define('viewApiDocs', function (?User $user = null) {
            return app()->environment('local', 'testing')
                || ($user && ($user->hasRole(['Super Administrator', 'IT Administrator']) || $user->can('api.manage')));
        });

        if (request()->header('X-Forwarded-Proto') === 'https' || request()->isSecure()) {
            URL::forceScheme('https');
        } elseif (app()->environment('production') && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }
    }
}
