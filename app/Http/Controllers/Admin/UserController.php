<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Report;
use App\Models\User;
use App\Rules\Username;
use App\Services\AvatarService;
use App\Services\StatsRecorder;
use App\Services\UserDeletion;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    private const PER_PAGE = 20;

    /** The short shape used in lists. */
    public static function brief(User $u): array
    {
        $p = $u->profile;

        return [
            'id' => $u->id, 'name' => $u->name, 'email' => $u->email, 'username' => $p?->username,
            'display' => $p ? ($p->name_fa ?: ($p->name_en ?: $p->username)) : $u->name,
            'has_avatar' => (bool) $p?->avatar_path, 'avatar_version' => $p?->avatar_version,
            'is_admin' => (bool) $u->is_admin, 'suspended' => $u->isSuspended(), 'created_at' => $u->created_at?->toIso8601String(),
            'last_login_at' => $u->last_login_at?->toIso8601String(),
        ];
    }

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $filter = (string) $request->query('filter', 'all');
        $sort = (string) $request->query('sort', 'newest');

        $query = User::query()->with('profile')->withCount(['sites', 'verifiedAccounts']);

        if ($q !== '') {
            $like = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], mb_strtolower($q)).'%';
            $query->where(function ($w) use ($like, $q) {
                $w->whereRaw('lower(users.email) like ?', [$like])->orWhereRaw('lower(users.name) like ?', [$like])
                    ->orWhereHas('profile', fn ($p) => $p->whereRaw('lower(username) like ?', [$like])->orWhereRaw("lower(coalesce(name_fa, '')) like ?", [$like])->orWhereRaw("lower(coalesce(name_en, '')) like ?", [$like]))
                    ->orWhereHas('emails', fn ($e) => $e->whereRaw('lower(email) like ?', [$like]));
                if (ctype_digit($q)) {
                    $w->orWhere('users.id', (int) $q);
                }
            });
        }

        match ($filter) {
            'admins' => $query->where('is_admin', true),
            'suspended' => $query->whereNotNull('suspended_at'),
            'unverified' => $query->whereDoesntHave('emails', fn ($e) => $e->whereNotNull('verified_at')),
            'with_sites' => $query->has('sites'),
            default => null,
        };

        match ($sort) {
            'oldest' => $query->orderBy('id'),
            'last_login' => $query->orderByRaw('last_login_at is null')->orderByDesc('last_login_at'),
            default => $query->orderByDesc('id'),
        };

        $page = $query->paginate(self::PER_PAGE);

        return response()->json([
            'data' => $page->getCollection()->map(fn (User $u) => self::brief($u) + ['sites' => $u->sites_count, 'verified_accounts' => $u->verified_accounts_count])->values(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'per_page' => self::PER_PAGE],
        ]);
    }

    public function show(StatsRecorder $stats, int $id): JsonResponse
    {
        $u = User::with(['profile', 'emails', 'sites', 'verifiedAccounts'])->withCount('avatars')->findOrFail($id);
        $p = $u->profile;
        $reports = $p ? Report::where('profile_id', $p->id) : null;

        return response()->json(self::brief($u) + [
            'suspended_at' => $u->suspended_at?->toIso8601String(), 'suspended_reason' => $u->suspended_reason,
            'profile' => $p?->only(['username', 'name_fa', 'name_en', 'headline', 'location', 'bio', 'website', 'socials', 'links', 'show_avatar', 'show_profile']),
            'emails' => $u->emails->map(fn ($e) => ['id' => $e->id, 'email' => $e->email, 'verified' => $e->isVerified(), 'primary' => (bool) $e->is_primary, 'public' => (bool) $e->is_public])->values(),
            'verified_accounts' => $u->verifiedAccounts->map(fn ($a) => ['id' => $a->id, 'network' => $a->network, 'identifier' => $a->identifier, 'method' => $a->method, 'url' => $a->url()])->values(),
            'sites' => $u->sites->map(fn ($s) => ['id' => $s->id, 'name' => $s->name, 'domain' => $s->domain, 'prefix' => $s->api_key_prefix, 'disabled' => $s->isDisabled(), 'last_used_at' => $s->last_used_at?->toIso8601String()])->values(),
            'avatars_count' => $u->avatars_count,
            'stats' => $stats->forUser($u->id, 30)['totals'],
            'reports' => ['total' => $reports?->count() ?? 0, 'open' => $reports?->where('status', 'open')->count() ?? 0],
            'audit' => AuditLog::where('target_type', 'user')->where('target_id', $u->id)->latest('id')->limit(10)->get()
                ->map(fn ($a) => ['action' => $a->action, 'by' => $a->admin_label, 'meta' => $a->meta, 'at' => $a->created_at?->toIso8601String()])->values(),
        ]);
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:255']]);
        $u = $this->target($request, $id);

        $u->forceFill(['suspended_at' => now(), 'suspended_reason' => $data['reason']])->save();
        AuditLog::record('user.suspend', $u, ['reason' => $data['reason']]);

        return response()->json(self::brief($u->fresh('profile')));
    }

    public function unsuspend(Request $request, int $id): JsonResponse
    {
        $u = User::findOrFail($id);
        $u->forceFill(['suspended_at' => null, 'suspended_reason' => null])->save();
        AuditLog::record('user.unsuspend', $u);

        return response()->json(self::brief($u->fresh('profile')));
    }

    public function admin(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['is_admin' => ['required', 'boolean']]);
        abort_if($id === $request->user()->id, 422, __('app.cannot_self'));
        $u = User::findOrFail($id);

        $u->forceFill(['is_admin' => $data['is_admin']])->save();
        AuditLog::record($data['is_admin'] ? 'user.promote' : 'user.demote', $u);

        return response()->json(self::brief($u->fresh('profile')));
    }

    public function rename(Request $request, int $id): JsonResponse
    {
        $u = User::with('profile')->findOrFail($id);
        abort_unless($u->profile, 404);
        $data = $request->validate(['username' => ['required', new Username, Rule::unique('profiles', 'username')->ignore($u->profile->id)]]);

        $old = $u->profile->username;
        $u->profile->update(['username' => $data['username']]);
        AuditLog::record('user.rename', $u, ['from' => $old, 'to' => $data['username']]);

        return response()->json(self::brief($u->fresh('profile')));
    }

    /** Moderation: take the photo down (and optionally the whole library). */
    public function removeAvatar(Request $request, AvatarService $avatars, int $id): JsonResponse
    {
        $data = $request->validate(['all' => ['sometimes', 'boolean']]);
        $u = User::with('profile')->findOrFail($id);
        abort_unless($u->profile, 404);

        if ($data['all'] ?? false) {
            foreach ($u->avatars as $a) {
                $avatars->destroy($u->profile, $a);
            }
        } else {
            $avatars->deactivate($u->profile);
        }
        AuditLog::record('user.avatar_removed', $u, ['library' => (bool) ($data['all'] ?? false)]);

        return response()->json(self::brief($u->fresh('profile')));
    }

    /** Moderation: blank out profile text that breaks the rules. */
    public function clearProfile(Request $request, int $id): JsonResponse
    {
        $data = $request->validate(['fields' => ['required', 'array', 'min:1'], 'fields.*' => ['in:bio,headline,location,website,links,socials,names']]);
        $u = User::with('profile')->findOrFail($id);
        abort_unless($u->profile, 404);

        $blank = ['bio' => ['bio' => null], 'headline' => ['headline' => null], 'location' => ['location' => null], 'website' => ['website' => null],
            'links' => ['links' => null], 'socials' => ['socials' => null], 'names' => ['name_fa' => null, 'name_en' => null]];
        $u->profile->update(array_merge(...array_map(fn ($f) => $blank[$f], array_unique($data['fields']))));
        AuditLog::record('user.profile_cleared', $u, ['fields' => array_values(array_unique($data['fields']))]);

        return response()->json(self::brief($u->fresh('profile')));
    }

    public function destroy(Request $request, UserDeletion $deletion, int $id): JsonResponse
    {
        $u = $this->target($request, $id);
        $data = $request->validate(['confirm' => ['required', 'string']]);
        abort_unless(hash_equals(mb_strtolower($u->email), mb_strtolower(trim($data['confirm']))), 422, __('app.wrong_confirmation'));

        AuditLog::record('user.delete', $u, ['email' => $u->email, 'username' => $u->profile?->username]);
        $deletion->delete($u);

        return response()->json(['message' => 'ok']);
    }

    /** A user that this administrator may act on: never themselves, never another administrator. */
    private function target(Request $request, int $id): User
    {
        abort_if($id === $request->user()->id, 422, __('app.cannot_self'));
        $u = User::with('profile')->findOrFail($id);
        abort_if($u->is_admin, 422, __('app.cannot_admin'));

        return $u;
    }
}
