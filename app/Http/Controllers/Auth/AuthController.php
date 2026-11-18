<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EmailController;
use App\Models\Profile;
use App\Models\User;
use App\Services\LoginFlow;
use App\Rules\Username;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        abort_unless(config('app.registration_open'), 403, __('app.registration_closed'));

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'username' => ['required', new Username, 'unique:profiles,username'],
            'accept_terms' => config('legal.require_acceptance') ? ['accepted'] : ['sometimes'],
        ], ['accept_terms.accepted' => __('app.accept_terms_required')]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            if (! empty($data['accept_terms'])) {
                $user->forceFill(['terms_accepted_at' => now()])->save();
            }
            Profile::create(['user_id' => $user->id, 'username' => $data['username'], 'name_en' => null]);

            return $user;
        });

        EmailController::attach($user, $data['email'], primary: true);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['message' => __('app.registered')], 201);
    }

    public function login(Request $request, LoginFlow $flow): JsonResponse
    {
        $cred = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string', 'max:1024']]);

        // Wrong passwords are counted per address+visitor (so a stranger cannot lock somebody out) and per address overall
        // (so guessing from many machines is slowed too). Signing in with an emailed code is a separate path and stays open.
        $email = \App\Models\UserEmail::normalize($cred['email']);
        $keys = ['login:'.sha1($email.'|'.$request->ip()) => 5, 'login-any:'.sha1($email) => 40];
        foreach ($keys as $k => $max) {
            if (\Illuminate\Support\Facades\RateLimiter::tooManyAttempts($k, $max)) {
                return response()->json(['message' => __('app.login_throttled', ['minutes' => ceil(\Illuminate\Support\Facades\RateLimiter::availableIn($k) / 60)])], 429);
            }
        }

        // Check the password WITHOUT signing in: a second factor may still be needed.
        $user = User::where('email', $email)->first();
        // an address with no account costs the same time as a wrong password, so timing does not reveal who has an account
        $ok = \Illuminate\Support\Facades\Hash::check($cred['password'], $user->password ?? \Illuminate\Support\Facades\Cache::rememberForever('dummy-hash', fn () => \Illuminate\Support\Facades\Hash::make(Str::random(20))));
        if (! $user || ! $ok) {
            foreach (array_keys($keys) as $k) {
                \Illuminate\Support\Facades\RateLimiter::hit($k, $k === array_key_first($keys) ? 900 : 3600);
            }

            return response()->json(['message' => __('app.bad_credentials')], 422);
        }
        foreach (array_keys($keys) as $k) {
            \Illuminate\Support\Facades\RateLimiter::clear($k);
        }
        if ($user->isSuspended()) {
            return response()->json(['message' => __('app.account_suspended')], 403);
        }

        return $flow->afterFirstFactor($request, $user, 'password');
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'ok']);
    }
}
