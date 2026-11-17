<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
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
        RateLimiter::for('avatar', fn (Request $r) => Limit::perMinute(600)->by($r->ip()));
    }
}
