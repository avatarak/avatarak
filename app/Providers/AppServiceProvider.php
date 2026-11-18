<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
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
        // Generous: real traffic should hit the CDN, this only stops origin abuse/hash probing.
        if ($proxies = config('app.trusted_proxies')) {
            TrustProxies::at($proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', (string) $proxies)))));
        }
        // Generated links (emails, QR codes, redirects) must be https when the site is served over https.
        if (app()->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        Event::listen(Login::class, fn (Login $e) => $e->user->forceFill(['last_login_at' => now()])->saveQuietly());

        RateLimiter::for('avatar', fn (Request $r) => Limit::perMinute(600)->by($r->ip()));
    }
}
