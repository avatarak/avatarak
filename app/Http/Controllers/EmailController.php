<?php

namespace App\Http\Controllers;

use App\Mail\VerifyEmailAddress;
use App\Models\User;
use App\Models\UserEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class EmailController extends Controller
{
    /** Create (or re-issue) an unverified claim for $address and send the verification mail. */
    public static function attach(User $user, string $address, bool $primary = false): UserEmail
    {
        $email = $user->emails()->firstOrNew(['email' => UserEmail::normalize($address)]);
        $email->is_primary = $email->exists ? $email->is_primary : $primary;
        $email->verification_token = Str::random(48);
        $email->save();

        Mail::to($email->email)->send(new VerifyEmailAddress($email));

        return $email;
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate(['email' => ['required', 'email:rfc', 'max:190']]);

        abort_if($request->user()->emails()->count() >= 10, 422, __('app.max_emails'));

        $email = self::attach($request->user(), $request->input('email'));

        return response()->json($this->present($email), 201);
    }

    public function resend(Request $request, int $id): JsonResponse
    {
        $email = $request->user()->emails()->findOrFail($id);
        abort_if($email->isVerified(), 422, __('app.email_already_verified'));

        self::attach($request->user(), $email->email);

        return response()->json(['message' => 'ok']);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['is_public' => ['required', 'boolean']]);
        $email = $request->user()->emails()->findOrFail($id);
        $email->update($data);

        return response()->json($this->present($email));
    }

    public function makePrimary(Request $request, int $id): JsonResponse
    {
        $email = $request->user()->emails()->findOrFail($id);
        abort_unless($email->isVerified(), 422, __('app.email_must_be_verified'));

        DB::transaction(function () use ($request, $email) {
            $request->user()->emails()->update(['is_primary' => false]);
            $email->update(['is_primary' => true]);
        });

        return response()->json($this->present($email->fresh()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $email = $request->user()->emails()->findOrFail($id);
        abort_if($email->is_primary, 422, __('app.email_primary_undeletable'));
        $email->delete();

        return response()->json(['message' => 'ok']);
    }

    /** Public link from the verification mail. */
    public function verify(string $token)
    {
        $email = UserEmail::where('verification_token', $token)->first();
        abort_unless($email, 404);

        $result = DB::transaction(function () use ($email) {
            $taken = UserEmail::where('email_hash', $email->email_hash)
                ->whereNotNull('verified_at')->where('id', '!=', $email->id)->exists();
            if ($taken) {
                return 'taken';
            }

            $email->forceFill(['verified_at' => now(), 'verification_token' => null])->save();
            // other users' unverified claims on this address lose
            UserEmail::where('email_hash', $email->email_hash)->whereNull('verified_at')->delete();

            return 'ok';
        });

        return response($result === 'ok'
            ? __('app.email_verified_ok')
            : __('app.email_taken'), $result === 'ok' ? 200 : 409);
    }

    private function present(UserEmail $e): array
    {
        return $e->only(['id', 'email', 'is_primary', 'is_public']) + ['verified' => $e->isVerified()];
    }
}
