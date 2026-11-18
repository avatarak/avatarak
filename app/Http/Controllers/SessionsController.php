<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * "Where am I signed in?" Works with the database session driver (SESSION_DRIVER=database, the default here).
 * The real session id is a secret, so the browser only ever sees an HMAC handle of it.
 */
class SessionsController extends Controller
{
    private function supported(): bool
    {
        return config('session.driver') === 'database';
    }

    private function handle(string $id): string
    {
        return substr(hash_hmac('sha256', $id, (string) config('app.key')), 0, 20);
    }

    public static function describe(?string $ua): array
    {
        $ua = (string) $ua;
        $browser = match (true) {
            (bool) preg_match('#Edg/#', $ua) => 'Edge', (bool) preg_match('#OPR/|Opera#', $ua) => 'Opera', (bool) preg_match('#Firefox/#', $ua) => 'Firefox',
            (bool) preg_match('#Chrome/|CriOS/#', $ua) => 'Chrome', (bool) preg_match('#Safari/#', $ua) => 'Safari', default => null,
        };
        $os = match (true) {
            (bool) preg_match('#iPhone|iPad|iOS#', $ua) => 'iOS', (bool) preg_match('#Android#', $ua) => 'Android', (bool) preg_match('#Windows#', $ua) => 'Windows',
            (bool) preg_match('#Mac OS X|Macintosh#', $ua) => 'macOS', (bool) preg_match('#Linux|X11#', $ua) => 'Linux', default => null,
        };

        return ['browser' => $browser, 'os' => $os];
    }

    public function index(Request $request): JsonResponse
    {
        if (! $this->supported()) {
            return response()->json(['supported' => false, 'sessions' => []]);
        }

        $current = $request->session()->getId();
        $rows = DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)->orderByDesc('last_activity')->get();

        return response()->json(['supported' => true, 'sessions' => $rows->map(fn ($r) => [
            'id' => $this->handle($r->id), 'current' => $r->id === $current, 'ip' => $r->ip_address,
            'last_active' => date('c', (int) $r->last_activity),
        ] + self::describe($r->user_agent))->values()]);
    }

    public function destroy(Request $request, string $handle): JsonResponse
    {
        abort_unless($this->supported(), 404);
        $mine = DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)->pluck('id');
        $id = $mine->first(fn ($sid) => hash_equals($this->handle($sid), $handle));
        abort_unless($id, 404);
        abort_if($id === $request->session()->getId(), 422, __('app.session_current'));

        DB::table(config('session.table', 'sessions'))->where('id', $id)->delete();

        return response()->json(['message' => 'ok']);
    }

    /** Sign out of every other browser and device. */
    public function destroyOthers(Request $request): JsonResponse
    {
        abort_unless($this->supported(), 404);
        $n = DB::table(config('session.table', 'sessions'))->where('user_id', $request->user()->id)->where('id', '!=', $request->session()->getId())->delete();

        return response()->json(['revoked' => $n]);
    }
}
