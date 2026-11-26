<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

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
    }
}
