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

        // switches an administrator changed in the panel win over .env
        app(\App\Services\Settings::class)->apply();

        // Tell the owner's webhooks about changes, whichever code path made them.
        $hooks = fn () => app(\App\Services\Webhooks::class);
        \App\Models\Profile::updated(function (\App\Models\Profile $p) use ($hooks) {
            $changed = array_values(array_diff(array_keys($p->getChanges()), ['updated_at']));
            if ($changed && $p->user) {
                $hooks()->dispatch($p->user, 'profile.updated', ['changed' => $changed]);
            }
        });
        \App\Models\Avatar::created(fn ($a) => $a->user && $hooks()->dispatch($a->user, 'avatar.changed'));
        \App\Models\Avatar::deleted(fn ($a) => $a->user && $hooks()->dispatch($a->user, 'avatar.changed'));
        \App\Models\VerifiedAccount::created(fn ($v) => $v->user && $hooks()->dispatch($v->user, 'verification.added', ['network' => $v->network, 'identifier' => $v->identifier]));
        \App\Models\VerifiedAccount::deleted(fn ($v) => $v->user && $hooks()->dispatch($v->user, 'verification.removed', ['network' => $v->network, 'identifier' => $v->identifier]));

        RateLimiter::for('avatar', fn (Request $r) => Limit::perMinute(600)->by($r->ip()));
    }
}
