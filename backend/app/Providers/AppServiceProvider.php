<?php

namespace App\Providers;

use App\Repositories\Contracts\DocumentRepositoryInterface;
use App\Repositories\DocumentRepository;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Schema;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DocumentRepositoryInterface::class, DocumentRepository::class);
    }

    public function boot(): void
    {
        Schema::defaultStringLength(191);
        ResetPassword::createUrlUsing(function (object $notifiable, string $token) {
            return config('app.frontend_url') . "/password-reset/$token?email={$notifiable->getEmailForPasswordReset()}";
        });

        Gate::before(function ($user, $ability) {
            return $user->hasPermission($ability) ? true : null;
        });

        foreach (config('permissions.roles', []) as $role => $permissions) {
            foreach ($permissions as $permission) {
                Gate::define($permission, function ($user) use ($permission) {
                    return $user->hasPermission($permission);
                });
            }
        }

        // Force fresh config in development to avoid cache issues
        if (config('app.env') === 'local' && config('app.debug')) {
            $this->forceFreshConfig();
        }
    }

    /**
     * Force fresh config loading in development to avoid stale cached values
     */
    private function forceFreshConfig(): void
    {
        // Reload critical config that might be cached
        config(['services.gemini.model' => env('GEMINI_MODEL', 'gemini-3.1-flash-lite')]);
        config(['services.gemini.api_key' => env('GEMINI_API_KEY')]);
        config(['services.gemini.verbose_logging' => env('GEMINI_VERBOSE_LOGGING', false)]);
    }
}
