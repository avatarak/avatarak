<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
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
        // There is no server-rendered login route; the exception handler below turns this into a 401.
        $middleware->redirectGuestsTo(fn () => '/');
        $middleware->web(append: [\App\Http\Middleware\SetLocale::class, \App\Http\Middleware\EnsureNotSuspended::class]);
        // the UI language cookie is set by JS in plain text (see resources/js/i18n.js)
        $middleware->encryptCookies(except: ['locale']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // No server-rendered login page: unauthenticated requests always get a 401 JSON
        $exceptions->render(function (\Illuminate\Auth\AuthenticationException $e, Request $request) {
            \App\Http\Middleware\SetLocale::apply($request);

            return response()->json(['message' => __('app.unauthenticated')], 401);
        });
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
