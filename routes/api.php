<?php

use App\Http\Controllers\Api\ProfileApiController;
use App\Http\Middleware\AuthenticateSite;
use Illuminate\Support\Facades\Route;

// Telegram calls this (authenticated by the secret header). Not part of the developer API.
Route::post('telegram/webhook', \App\Http\Controllers\Api\TelegramWebhookController::class)->name('telegram.webhook');

Route::prefix('v1')->middleware(AuthenticateSite::class)->group(function () {
    Route::get('profiles/{hash}', [ProfileApiController::class, 'show'])
        ->where('hash', '[A-Fa-f0-9]{64}');
    Route::post('profiles/lookup', [ProfileApiController::class, 'lookup']);
});
