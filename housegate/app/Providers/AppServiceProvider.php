<?php

namespace App\Providers;

use Illuminate\Support\Facades\URL;
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
        // Behind a tunnel the Host header is rewritten (housegate-qr.test), so build
        // every generated URL (redirects, routes, assets) from APP_URL instead.
        if (config('app.url')) {
            URL::forceRootUrl(config('app.url'));
        }
    }
}
