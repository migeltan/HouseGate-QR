<?php

namespace App\Providers;

use App\Mail\Transport\BrevoApiTransport;
use Illuminate\Support\Facades\Mail;
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

        // Mail over HTTPS (Brevo API) for hosts that block SMTP ports.
        Mail::extend('brevo', fn (array $config) => new BrevoApiTransport((string) ($config['key'] ?? '')));
    }
}