<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Schema::defaultStringLength(191);

        // This helper is not available on every installed Laravel version.
        if (method_exists(Schema::getFacadeRoot(), 'useNativeSchemaOperationsIfPossible')) {
            Schema::useNativeSchemaOperationsIfPossible();
        }

        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');
            $normalizedEmail = Str::transliterate(Str::lower(is_string($email) ? trim($email) : ''));

            return [
                Limit::perMinute(6)->by('email:'.$normalizedEmail.'|'.$request->ip()),
                Limit::perMinute(30)->by('ip:'.$request->ip()),
            ];
        });
    }
}
