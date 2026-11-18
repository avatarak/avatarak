<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Visitors report a public profile (spam, impersonation, an inappropriate photo…). */
class ReportController extends Controller
{
    public function store(Request $request, string $username): JsonResponse
    {
        $profile = Profile::visible()->where('username', strtolower($username))->firstOrFail();

        $data = $request->validate([
            'reason' => ['required', 'in:'.implode(',', Report::REASONS)],
            'details' => ['nullable', 'string', 'max:500'],
        ]);

        // A keyed hash of the IP is kept only to ignore the same person reporting the same profile again today.
        $who = substr(hash_hmac('sha256', (string) $request->ip(), (string) config('app.key')), 0, 32);
        $dup = Report::where('profile_id', $profile->id)->where('reporter_hash', $who)->where('created_at', '>', now()->subDay())->exists();

        if (! $dup && $profile->user_id !== $request->user()?->id) {
            Report::create(['profile_id' => $profile->id, 'reason' => $data['reason'], 'details' => $data['details'] ?? null, 'reporter_hash' => $who]);
        }

        // Always the same answer, so it cannot be used to probe.
        return response()->json(['message' => __('app.report_thanks')]);
    }
}
