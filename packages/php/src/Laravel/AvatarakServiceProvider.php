<?php

namespace Avatarak\Laravel;

use Avatarak\Client;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;

class AvatarakServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../../config/avatarak.php', 'avatarak');

        $this->app->singleton(Client::class, fn ($app) => new Client(
            config('avatarak.key'), config('avatarak.base_url'), (int) config('avatarak.timeout'),
        ));
        $this->app->alias(Client::class, 'avatarak');
    }

    public function boot(): void
    {
        $this->publishes([__DIR__.'/../../config/avatarak.php' => $this->app->configPath('avatarak.php')], 'avatarak-config');

        // <x-avatarak-avatar :email="$user->email" :size="64" class="rounded-full" alt="" />
        Blade::component('avatarak-avatar', View\Avatar::class);
    }
}
