<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // Public avatar endpoint: no session/cookies so CDNs can cache it.
            \Illuminate\Support\Facades\Route::get('/avatar/{hash}', [\App\Http\Controllers\AvatarController::class, 'show'])
                ->where('hash', '[A-Fa-f0-9]{64}')
                ->middleware('throttle:avatar')
                ->name('avatar');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        //
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
