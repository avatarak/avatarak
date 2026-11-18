<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use ZipArchive;

/** Everything we hold about one user, as a ZIP: data.json, the photos, and a README. Never secrets or other people's data. */
class DataExport
{
    /** @return string path of a temporary zip file (the caller deletes it) */
    public function build(User $user): string
    {
        $user->loadMissing('profile', 'emails', 'verifiedAccounts', 'sites', 'avatars');
        $p = $user->profile;
        $disk = Storage::disk(config('avatar.disk'));

        $avatars = [];
        $files = [];
        foreach ($user->avatars as $i => $a) {
            if ($disk->exists($a->path)) {
                $name = 'avatars/photo-'.($i + 1).'.jpg';
                $files[$name] = $a->path;
                $avatars[] = ['file' => $name, 'uploaded_at' => $a->created_at?->toIso8601String(), 'active' => $p?->avatar_id === $a->id];
            }
        }

        $data = [
            'exported_at' => now()->toIso8601String(),
            'service' => config('brand.name_en') ?: config('brand.name'),
            'account' => [
                'name' => $user->name, 'email' => $user->email, 'created_at' => $user->created_at?->toIso8601String(),
                'last_login_at' => $user->last_login_at?->toIso8601String(), 'terms_accepted_at' => $user->terms_accepted_at?->toIso8601String(),
                'two_factor_enabled' => $user->hasTwoFactor(),
            ],
            'profile' => $p ? [
                'username' => $p->username, 'name_fa' => $p->name_fa, 'name_en' => $p->name_en, 'headline' => $p->headline, 'location' => $p->location,
                'bio' => $p->bio, 'website' => $p->website, 'socials' => $p->socials, 'links' => collect($p->links ?? [])->map(fn ($l) => collect($l)->only(['label', 'url', 'description'])->all())->all(),
                'accent_color' => $p->accent_color, 'show_avatar' => (bool) $p->show_avatar, 'show_profile' => (bool) $p->show_profile, 'allow_indexing' => (bool) $p->allow_indexing,
            ] : null,
            'emails' => $user->emails->map(fn ($e) => [
                'email' => $e->email, 'verified' => $e->isVerified(), 'primary' => (bool) $e->is_primary, 'public' => (bool) $e->is_public, 'added_at' => $e->created_at?->toIso8601String(),
            ])->values()->all(),
            'verified_accounts' => $user->verifiedAccounts->map(fn ($a) => [
                'network' => $a->network, 'identifier' => $a->identifier, 'method' => $a->method, 'verified_at' => $a->verified_at?->toIso8601String(),
            ])->values()->all(),
            'sites' => $user->sites->map(fn ($s) => [
                'name' => $s->name, 'domain' => $s->domain, 'key_prefix' => $s->api_key_prefix, 'rate_limit_per_minute' => $s->rate_limit_per_minute,
                'created_at' => $s->created_at?->toIso8601String(), 'last_used_at' => $s->last_used_at?->toIso8601String(),
            ])->values()->all(),
            'avatars' => $avatars,
            'statistics' => [
                'daily' => DB::table('daily_stats')->where('user_id', $user->id)->orderBy('date')->get(['date', 'metric', 'count'])->all(),
                'referrers' => DB::table('stat_referrers')->where('user_id', $user->id)->orderBy('date')->get(['date', 'metric', 'host', 'count'])->all(),
            ],
            'sessions' => config('session.driver') === 'database'
                ? DB::table(config('session.table', 'sessions'))->where('user_id', $user->id)->get(['ip_address', 'user_agent', 'last_activity'])
                    ->map(fn ($s) => ['ip' => $s->ip_address, 'user_agent' => $s->user_agent, 'last_active' => date('c', (int) $s->last_activity)])->all()
                : [],
        ];

        $path = tempnam(sys_get_temp_dir(), 'avatarak-export-');
        $zip = new ZipArchive;
        if ($zip->open($path, ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Could not create the export file.');
        }
        $zip->addFromString('data.json', json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        foreach ($files as $name => $src) {
            $zip->addFromString($name, (string) $disk->get($src));
        }
        $zip->addFromString('README.txt', "Your data from ".($data['service'])."\nExported: {$data['exported_at']}\n\n"
            ."data.json  - your account, profile, emails, verified accounts, sites (without API keys), statistics and sessions\n"
            ."avatars/   - the photos you uploaded\n\n"
            ."Passwords, two-step secrets and API keys are never included. فایل data.json را با هر ویرایشگر متن می‌توانید بخوانید.\n");
        $zip->close();

        return $path;
    }
}
