<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AvatarController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\SiteController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app');
Route::get('/up/ready', \App\Http\Controllers\ReadyController::class)->middleware('throttle:60,1');

// Public
Route::get('/u/{username}', fn (string $username) => redirect()->route('profile.show', strtolower($username), 301))->where('username', '[A-Za-z][A-Za-z0-9_]{2,31}');
Route::get('/verify-email/{token}', [EmailController::class, 'verify'])->name('email.verify')->middleware('throttle:20,1');

// Auth (session based, same-origin SPA)
Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/login/code', [\App\Http\Controllers\Auth\CodeLoginController::class, 'send'])->middleware('throttle:6,1');
Route::post('/login/code/verify', [\App\Http\Controllers\Auth\CodeLoginController::class, 'verify'])->middleware('throttle:10,1');
Route::post('/login/2fa', [\App\Http\Controllers\Auth\TwoFactorLoginController::class, 'verify'])->middleware('throttle:10,1');
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

    Route::delete('/', [\App\Http\Controllers\SecurityController::class, 'destroy'])->middleware('throttle:5,1');
    Route::get('2fa', [\App\Http\Controllers\TwoFactorController::class, 'status']);
    Route::post('2fa/setup', [\App\Http\Controllers\TwoFactorController::class, 'setup'])->middleware('throttle:10,1');
    Route::post('2fa/confirm', [\App\Http\Controllers\TwoFactorController::class, 'confirm'])->middleware('throttle:10,1');
    Route::post('2fa/disable', [\App\Http\Controllers\TwoFactorController::class, 'disable'])->middleware('throttle:10,1');
    Route::post('2fa/recovery-codes', [\App\Http\Controllers\TwoFactorController::class, 'regenerate'])->middleware('throttle:10,1');
    Route::get('sessions', [\App\Http\Controllers\SessionsController::class, 'index']);
    Route::delete('sessions', [\App\Http\Controllers\SessionsController::class, 'destroyOthers']);
    Route::delete('sessions/{handle}', [\App\Http\Controllers\SessionsController::class, 'destroy'])->where('handle', '[a-f0-9]{20}');
    Route::get('stats', [\App\Http\Controllers\StatsController::class, 'show']);
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

// Administration: the UI is part of the same single-page app, the data comes from /admin/api (admins only).
Route::view('/admin', 'app');
Route::prefix('admin/api')->middleware(['auth', 'admin'])->group(function () {
    Route::get('overview', [\App\Http\Controllers\Admin\OverviewController::class, 'show']);

    Route::get('users', [\App\Http\Controllers\Admin\UserController::class, 'index']);
    Route::get('users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'show'])->whereNumber('id');
    Route::post('users/{id}/suspend', [\App\Http\Controllers\Admin\UserController::class, 'suspend'])->whereNumber('id');
    Route::post('users/{id}/unsuspend', [\App\Http\Controllers\Admin\UserController::class, 'unsuspend'])->whereNumber('id');
    Route::post('users/{id}/admin', [\App\Http\Controllers\Admin\UserController::class, 'admin'])->whereNumber('id');
    Route::post('users/{id}/rename', [\App\Http\Controllers\Admin\UserController::class, 'rename'])->whereNumber('id');
    Route::post('users/{id}/avatar/remove', [\App\Http\Controllers\Admin\UserController::class, 'removeAvatar'])->whereNumber('id');
    Route::post('users/{id}/profile/clear', [\App\Http\Controllers\Admin\UserController::class, 'clearProfile'])->whereNumber('id');
    Route::delete('users/{id}', [\App\Http\Controllers\Admin\UserController::class, 'destroy'])->whereNumber('id');

    Route::get('reports', [\App\Http\Controllers\Admin\ReportController::class, 'index']);
    Route::post('reports/{id}/resolve', [\App\Http\Controllers\Admin\ReportController::class, 'resolve'])->whereNumber('id');
    Route::post('reports/{id}/reopen', [\App\Http\Controllers\Admin\ReportController::class, 'reopen'])->whereNumber('id');

    Route::get('sites', [\App\Http\Controllers\Admin\SiteController::class, 'index']);
    Route::patch('sites/{id}', [\App\Http\Controllers\Admin\SiteController::class, 'update'])->whereNumber('id');
    Route::delete('sites/{id}', [\App\Http\Controllers\Admin\SiteController::class, 'destroy'])->whereNumber('id');

    Route::get('verified', [\App\Http\Controllers\Admin\VerifiedController::class, 'index']);
    Route::delete('verified/{id}', [\App\Http\Controllers\Admin\VerifiedController::class, 'destroy'])->whereNumber('id');

    Route::get('audit', [\App\Http\Controllers\Admin\AuditController::class, 'index']);
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
Route::post('/{username}/report', [\App\Http\Controllers\ReportController::class, 'store'])
    ->where('username', '[A-Za-z][A-Za-z0-9_]{2,31}')->middleware('throttle:6,1')->name('profile.report');
Route::get('/{username}/print', [\App\Http\Controllers\ProfileExportController::class, 'printCard'])
    ->where('username', '[A-Za-z][A-Za-z0-9_]{2,31}')->middleware('throttle:60,1')->name('profile.print');
Route::get('/{username}/share.svg', [\App\Http\Controllers\ProfileExportController::class, 'shareImage'])
    ->where('username', '[A-Za-z][A-Za-z0-9_]{2,31}')->middleware('throttle:60,1')->name('profile.share');
Route::get('/{username}/avatar', [AvatarController::class, 'profile'])
    ->where('username', '[A-Za-z][A-Za-z0-9_]{2,31}')->middleware('throttle:avatar')->name('profile.avatar');
