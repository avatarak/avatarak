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
Route::post('/login/code', [\App\Http\Controllers\Auth\CodeLoginController::class, 'send'])->middleware('throttle:6,1');
Route::post('/login/code/verify', [\App\Http\Controllers\Auth\CodeLoginController::class, 'verify'])->middleware('throttle:10,1');
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

    Route::get('verifications', [\App\Http\Controllers\VerificationController::class, 'index']);
    Route::post('verifications/website', [\App\Http\Controllers\VerificationController::class, 'website'])->middleware('throttle:20,1');
    Route::post('verifications/website/check', [\App\Http\Controllers\VerificationController::class, 'websiteCheck'])->middleware('throttle:10,1');
    Route::post('verifications/telegram', [\App\Http\Controllers\VerificationController::class, 'telegram'])->middleware('throttle:20,1');
    Route::post('verifications/github', [\App\Http\Controllers\VerificationController::class, 'github'])->middleware('throttle:20,1');
    Route::post('verifications/github/check', [\App\Http\Controllers\VerificationController::class, 'githubCheck'])->middleware('throttle:10,1');
    Route::get('verifications/github/redirect', [\App\Http\Controllers\VerificationController::class, 'githubRedirect'])->middleware('throttle:20,1');
    Route::get('verifications/github/callback', [\App\Http\Controllers\VerificationController::class, 'githubCallback'])->middleware('throttle:20,1')->name('verify.github.callback');
    Route::delete('verifications/{id}', [\App\Http\Controllers\VerificationController::class, 'destroy']);

    Route::patch('password', [\App\Http\Controllers\SecurityController::class, 'password'])->middleware('throttle:10,1');

    Route::get('sites', [SiteController::class, 'index']);
    Route::post('sites', [SiteController::class, 'store'])->middleware('throttle:10,1');
    Route::delete('sites/{id}', [SiteController::class, 'destroy']);

    Route::post('emails', [EmailController::class, 'store'])->middleware('throttle:10,1');
    Route::patch('emails/{id}', [EmailController::class, 'update']);
    Route::post('emails/{id}/primary', [EmailController::class, 'makePrimary']);
    Route::post('emails/{id}/resend', [EmailController::class, 'resend'])->middleware('throttle:5,1');
    Route::delete('emails/{id}', [EmailController::class, 'destroy']);
});

// Documentation (Markdown in resources/docs); must come before the /{username} catch-all.
Route::get('/docs', [\App\Http\Controllers\DocsController::class, 'index'])->name('docs.index');
Route::get('/docs/llms.txt', [\App\Http\Controllers\DocsController::class, 'llms'])->name('docs.llms');
Route::get('/docs/{slug}', [\App\Http\Controllers\DocsController::class, 'show'])->where('slug', '[a-z0-9-]+')->name('docs.show');

// Stored link-preview thumbnails (public: they are copies of public og:images)
Route::get('/thumb/{key}', function (string $key) {
    $path = \App\Services\LinkPreviewService::path($key);
    $disk = \Illuminate\Support\Facades\Storage::disk(config('avatar.disk'));
    abort_unless($disk->exists($path), 404);

    return response($disk->get($path), 200, [
        'Content-Type' => 'image/jpeg',
        'Cache-Control' => 'public, max-age=86400',
        'X-Content-Type-Options' => 'nosniff',
    ]);
})->where('key', '[a-f0-9]{40}')->middleware('throttle:avatar')->name('link.thumb');

// Machine-readable / embeddable renderings: /{username}.json | .md | .vcf | .card | .qr
Route::get('/{username}.{format}', [\App\Http\Controllers\ProfileExportController::class, 'show'])
    ->where(['username' => '[A-Za-z][A-Za-z0-9_]{2,31}', 'format' => 'json|md|vcf|card|qr'])
    ->middleware('throttle:60,1')->name('profile.export');

// Public profiles live at the site root, like gravatar.com/{username}. Keep these LAST so they never
// shadow another route; usernames that collide with routes are reserved (App\Rules\Username).
Route::get('/{username}', [PublicProfileController::class, 'show'])
    ->where('username', '[A-Za-z][A-Za-z0-9_]{2,31}')->name('profile.show');
Route::get('/{username}/print', [\App\Http\Controllers\ProfileExportController::class, 'printCard'])
    ->where('username', '[A-Za-z][A-Za-z0-9_]{2,31}')->middleware('throttle:60,1')->name('profile.print');
Route::get('/{username}/share.svg', [\App\Http\Controllers\ProfileExportController::class, 'shareImage'])
    ->where('username', '[A-Za-z][A-Za-z0-9_]{2,31}')->middleware('throttle:60,1')->name('profile.share');
Route::get('/{username}/avatar', [AvatarController::class, 'profile'])
    ->where('username', '[A-Za-z][A-Za-z0-9_]{2,31}')->middleware('throttle:avatar')->name('profile.avatar');
