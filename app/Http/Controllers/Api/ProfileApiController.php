<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProfileResource;
use App\Models\Profile;
use App\Models\UserEmail;
use App\Services\StatsRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProfileApiController extends Controller
{
    public const MAX_BATCH = 100;

    public function show(Request $request, string $hash): JsonResponse
    {
        $hash = strtolower($hash);
        $profile = $this->resolve([$hash])[$hash] ?? null;

        // Same 404 for unknown, unverified, private or hidden: no probing.
        abort_unless($profile, 404, 'Profile not found.');

        StatsRecorder::record($profile->user_id, 'api', null, $request->attributes->get('site')?->domain);

        return (new ProfileResource($profile, $hash))->response()
            ->header('Cache-Control', 'private, max-age=60');
    }

    public function lookup(Request $request): JsonResponse
    {
        $data = $request->validate([
            'hashes' => ['required', 'array', 'min:1', 'max:'.self::MAX_BATCH],
            'hashes.*' => ['required', 'string', 'regex:/^[A-Fa-f0-9]{64}$/'],
        ]);

        $hashes = array_values(array_unique(array_map('strtolower', $data['hashes'])));
        $found = $this->resolve($hashes);

        $out = [];
        foreach ($found as $profile) {
            StatsRecorder::record($profile->user_id, 'api', null, $request->attributes->get('site')?->domain);
        }
        foreach ($hashes as $h) {
            $out[$h] = isset($found[$h]) ? (new ProfileResource($found[$h], $h))->resolve() : null;
        }

        return response()->json(['data' => $out])->header('Cache-Control', 'private, max-age=60');
    }

    /**
     * @param  list<string>  $hashes
     * @return array<string, Profile> keyed by hash; only publicly visible profiles
     */
    private function resolve(array $hashes): array
    {
        $valid = array_filter($hashes, fn ($h) => preg_match('/^[a-f0-9]{64}$/', $h));
        if (! $valid) {
            return [];
        }

        $emails = UserEmail::with('user.profile')
            ->whereIn('email_hash', $valid)
            ->whereNotNull('verified_at')
            ->where('is_public', true)
            ->get();

        $out = [];
        foreach ($emails as $e) {
            $profile = $e->user?->profile;
            if ($profile && $profile->show_profile && ! $e->user->isSuspended()) {
                $out[$e->email_hash] = $profile;
            }
        }

        return $out;
    }
}
