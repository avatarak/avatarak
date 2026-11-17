<?php

namespace App\Services;

use App\Models\Profile;
use App\Models\UserEmail;

/**
 * Public, machine-readable renderings of a profile (.json / .md / .vcf).
 * Only data the owner made public; emails are never included.
 */
class ProfileExport
{
    public const SOCIAL_URLS = [
        'telegram' => 'https://t.me/%s', 'instagram' => 'https://instagram.com/%s', 'x' => 'https://x.com/%s',
        'github' => 'https://github.com/%s', 'linkedin' => 'https://linkedin.com/in/%s', 'youtube' => 'https://youtube.com/@%s',
        'whatsapp' => 'https://wa.me/%s', 'facebook' => 'https://facebook.com/%s', 'eitaa' => 'https://eitaa.com/%s', 'rubika' => 'https://rubika.ir/%s',
    ];

    public function display(Profile $p): string
    {
        return $p->name_fa ?: ($p->name_en ?: $p->username);
    }

    /** @return array<string, string> network => absolute url */
    public function socials(Profile $p): array
    {
        $out = [];
        foreach ($p->socials ?? [] as $net => $handle) {
            if (isset(self::SOCIAL_URLS[$net]) && $handle !== '') {
                $out[$net] = sprintf(self::SOCIAL_URLS[$net], rawurlencode($handle));
            }
        }

        return $out;
    }

    /** @return list<array{label:string,url:string,description?:string,image?:string}> */
    public function links(Profile $p): array
    {
        $images = $p->show_link_images;

        return collect($p->links ?? [])
            ->filter(fn ($l) => ! empty($l['label']) && ! empty($l['url']) && preg_match('#^https?://#i', $l['url']))
            ->map(fn ($l) => array_filter([
                'label' => $l['label'], 'url' => $l['url'], 'description' => $l['description'] ?? null,
                'image' => ($images && ! empty($l['thumb'])) ? route('link.thumb', $l['thumb']) : null,
            ], fn ($v) => $v !== null && $v !== ''))
            ->values()->all();
    }

    public function avatarUrl(Profile $p, int $size = 512): ?string
    {
        return ($p->show_avatar && $p->avatar_path)
            ? route('profile.avatar', ['username' => $p->username, 's' => $size, 'v' => $p->avatar_version]) : null;
    }

    public function verified(Profile $p): bool
    {
        return UserEmail::where('user_id', $p->user_id)->whereNotNull('verified_at')->exists();
    }

    public function data(Profile $p): array
    {
        return [
            'username' => $p->username,
            'url' => route('profile.show', $p->username),
            'display_name' => $this->display($p),
            'name_fa' => $p->name_fa,
            'name_en' => $p->name_en,
            'headline' => $p->headline,
            'location' => $p->location,
            'bio' => $p->bio,
            'website' => $p->website,
            'avatar_url' => $this->avatarUrl($p),
            'verified' => $this->verified($p),
            'socials' => (object) $this->socials($p),
            'links' => $this->links($p),
            'created_at' => $p->created_at?->toIso8601String(),
            'updated_at' => $p->updated_at?->toIso8601String(),
        ];
    }

    public function markdown(Profile $p): string
    {
        $esc = fn (string $s) => str_replace(['\\', '[', ']'], ['\\\\', '\\[', '\\]'], $s);
        $lines = ['# '.str_replace(["\r", "\n"], ' ', $this->display($p)), ''];

        $sub = array_filter([$p->headline, $p->location]);
        if ($sub) {
            $lines[] = '_'.str_replace(['_', "\n"], ['\\_', ' '], implode(' · ', $sub)).'_';
            $lines[] = '';
        }
        if ($p->bio) {
            $lines[] = $p->bio;
            $lines[] = '';
        }
        $lines[] = '- Profile: <'.route('profile.show', $p->username).'>';
        if ($p->website) {
            $lines[] = '- Website: <'.$p->website.'>';
        }
        foreach ($this->socials($p) as $net => $url) {
            $lines[] = '- '.ucfirst($net).': <'.$url.'>';
        }
        if ($links = $this->links($p)) {
            $lines[] = '';
            $lines[] = '## Links';
            $lines[] = '';
            foreach ($links as $l) {
                $lines[] = '- ['.$esc($l['label']).']('.$l['url'].')'.(isset($l['description']) ? ' — '.str_replace(["\r", "\n"], ' ', $l['description']) : '');
            }
        }

        return implode("\n", $lines)."\n";
    }

    public function vcard(Profile $p): string
    {
        $esc = fn (string $s) => str_replace(['\\', ';', ',', "\r\n", "\n", "\r"], ['\\\\', '\;', '\\,', '\\n', '\\n', '\\n'], $s);
        $name = $this->display($p);

        $rows = ['BEGIN:VCARD', 'VERSION:3.0', 'FN:'.$esc($name), 'N:'.$esc($name).';;;;'];
        if ($p->headline) {
            $rows[] = 'TITLE:'.$esc($p->headline);
        }
        if ($p->location) {
            $rows[] = 'ADR;TYPE=HOME:;;;'.$esc($p->location).';;;';
        }
        $rows[] = 'URL:'.route('profile.show', $p->username);
        if ($p->website) {
            $rows[] = 'URL;TYPE=WORK:'.$p->website;
        }
        foreach ($this->socials($p) as $net => $url) {
            $rows[] = 'X-SOCIALPROFILE;TYPE='.$net.':'.$url;
        }
        if ($p->bio) {
            $rows[] = 'NOTE:'.$esc($p->bio);
        }
        if ($avatar = $this->avatarUrl($p, 256)) {
            $rows[] = 'PHOTO;VALUE=URI:'.$avatar;
        }
        $rows[] = 'END:VCARD';

        return implode("\r\n", array_map([$this, 'fold'], $rows))."\r\n";
    }

    /** RFC 6350 line folding at 75 octets, never splitting a UTF-8 character. */
    private function fold(string $line): string
    {
        $out = '';
        $cur = '';
        foreach (preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $ch) {
            if (strlen($cur) + strlen($ch) > 75) {
                $out .= $cur."\r\n ";
                $cur = '';
            }
            $cur .= $ch;
        }

        return $out.$cur;
    }
}
