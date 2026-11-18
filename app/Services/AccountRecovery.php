<?php

namespace App\Services;

use App\Models\CustomDomain;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Anybody can sign up with somebody else's email address (we do not make people verify before they use the app).
 * The first time the REAL owner of that address proves it (by clicking the link we mailed, or with a one-time code),
 * whatever the sign-up person set up around the account is not to be trusted: their password, sessions, extra sign-in
 * emails, authenticator, API keys, webhooks and "verified" accounts. Without this, an attacker who registered a
 * victim's address first would keep a way into the victim's account after the victim confirms it ("pre-hijacking").
 */
class AccountRecovery
{
    /** True when proving $email is the first time the owner of the account's sign-up address does so. */
    public static function isSignupAddressFirstProof(User $user, UserEmail $email): bool
    {
        return ! $email->verified_at && UserEmail::normalize($email->email) === UserEmail::normalize($user->email);
    }

    /** Keep the account and its profile, drop every way in that somebody else may have set up. */
    public function reclaim(User $user, UserEmail $keep): void
    {
        DB::transaction(function () use ($user, $keep) {
            DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->delete();
            $user->emails()->where('id', '!=', $keep->id)->delete();
            $user->forceFill([
                'password' => Str::random(48),          // unknown to everybody: the owner sets a new one after signing in with a code
                'remember_token' => null,
                'two_factor_secret' => null, 'two_factor_recovery_codes' => null, 'two_factor_confirmed_at' => null, 'two_factor_last_step' => null,
            ])->save();
            $user->sites()->delete();
            $user->webhooks()->delete();
            $user->verifiedAccounts()->delete();
            CustomDomain::where('user_id', $user->id)->delete();
        });
    }
}
