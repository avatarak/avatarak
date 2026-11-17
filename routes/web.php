<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app');

// Public
Route::get('/u/{username}', fn (string $username) => redirect()->route('profile.show', strtolower($username), 301))->where('username', '[A-Za-z][A-Za-z0-9_]{2,31}');
Route::get('/verify-email/{token}', [EmailController::class, 'verify'])->name('email.verify')->middleware('throttle:20,1');

// Auth (session based, same-origin SPA)
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');

Route::middleware('auth')->prefix('account')->group(function () {
    Route::get('/', [AccountController::class, 'show']);
    Route::patch('profile', [AccountController::class, 'updateProfile']);
    Route::patch('appearance', [AccountController::class, 'updateAppearance']);
    Route::patch('privacy', [AccountController::class, 'updatePrivacy']);
    Route::post('avatar', [AccountController::class, 'uploadAvatar'])->middleware('throttle:20,1');
    Route::get('avatar', [AccountController::class, 'myAvatar']);
    Route::post('avatars', [AccountController::class, 'uploadAvatar'])->middleware('throttle:20,1');
    Route::get('avatars/{id}/image', [AccountController::class, 'avatarImage']);
    Route::post('avatars/{id}/activate', [AccountController::class, 'activateAvatar']);
    Route::delete('avatars/{id}', [AccountController::class, 'destroyAvatar']);
    Route::delete('avatar', [AccountController::class, 'deleteAvatar']);

    Route::get('sites', [SiteController::class, 'index']);
    Route::post('sites', [SiteController::class, 'store'])->middleware('throttle:10,1');
    Route::delete('sites/{id}', [SiteController::class, 'destroy']);

    Route::post('emails', [EmailController::class, 'store'])->middleware('throttle:10,1');
    Route::patch('emails/{id}', [EmailController::class, 'update']);
    Route::post('emails/{id}/primary', [EmailController::class, 'makePrimary']);
    Route::post('emails/{id}/resend', [EmailController::class, 'resend'])->middleware('throttle:5,1');
    Route::delete('emails/{id}', [EmailController::class, 'destroy']);
});

// Public profiles live at the site root, like gravatar.com/{username}. Keep these LAST so they never
// shadow another route; usernames that collide with routes are reserved (App\Rules\Username).
Route::get('/{username}', [PublicProfileController::class, 'show'])
    ->where('username', '[A-Za-z][A-Za-z0-9_]{2,31}')->name('profile.show');
Route::get('/{username}/avatar', [AvatarController::class, 'profile'])
    ->where('username', '[A-Za-z][A-Za-z0-9_]{2,31}')->middleware('throttle:avatar')->name('profile.avatar');
