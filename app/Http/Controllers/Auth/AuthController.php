<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Controllers\EmailController;
use App\Models\Profile;
use App\Models\User;
use App\Rules\Username;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rules\Password;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'password' => ['required', Password::min(8)],
            'username' => ['required', new Username, 'unique:profiles,username'],
        ]);

        $user = DB::transaction(function () use ($data) {
            $user = User::create(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
            Profile::create(['user_id' => $user->id, 'username' => $data['username'], 'name_en' => null]);

            return $user;
        });

        EmailController::attach($user, $data['email'], primary: true);

        Auth::login($user);
        $request->session()->regenerate();

        return response()->json(['message' => __('app.registered')], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $cred = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::attempt($cred)) {
            return response()->json(['message' => __('app.bad_credentials')], 422);
        }

        if (Auth::user()->isSuspended()) {
            Auth::logout();
            $request->session()->invalidate();

            return response()->json(['message' => __('app.account_suspended')], 403);
        }

        $request->session()->regenerate();

        return response()->json(['message' => 'ok']);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'ok']);
    }
}
