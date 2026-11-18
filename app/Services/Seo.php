<?php

namespace App\Services;

use App\Models\Profile;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** Everything a search engine or a link preview wants to know about a public profile page. */
class Seo
{
    public const OG_LOCALES = ['fa' => 'fa_IR', 'en' => 'en_US'];

    /**
     * @param Profile $p localized copy (what the page shows); $orig is the untouched profile.
     * @return array{canonical:string,alternates:array<string,string>,description:string,title:string,image:?string,image_alt:string,locale:string,alt_locales:list<string>,json_ld:?string}
     */
    public function profile(Profile $p, Profile $orig, Request $request, string $display, string $brand, bool $indexable): array
    {
        $export = app(ProfileExport::class);
        $lang = $p->lang ?? $p->defaultLang();
        // on the owner's own domain that address is the canonical one; otherwise ours
        $base = $request->attributes->has('custom_profile') ? $request->getSchemeAndHttpHost() : route('profile.show', $p->username);
        $urlFor = fn (string $l) => $l === $orig->defaultLang() ? $base : $base.'?lang='.$l;

        $alternates = [];
        if ($orig->languages === 'both') {
            foreach (Profile::LANGS as $l) {
                $alternates[$l] = $urlFor($l);
            }
            $alternates['x-default'] = $base;
        }

        $description = $this->description($p, $display, $brand, $lang);
        $image = ($p->show_avatar && $p->avatar_path)
            ? route('profile.avatar', ['username' => $p->username, 's' => 512, 'v' => $p->avatar_version])
            : route('profile.avatar', ['username' => $p->username, 's' => 512, 'd' => 'initials']);

        return [
            'canonical' => $urlFor($lang),
            'alternates' => $alternates,
            'description' => $description,
            'title' => $display.' — '.$brand,
            'image' => $image,
            'image_alt' => $display,
            'locale' => self::OG_LOCALES[$lang] ?? 'fa_IR',
            'alt_locales' => $orig->languages === 'both' ? array_values(array_filter(array_map(fn ($l) => $l !== $lang ? self::OG_LOCALES[$l] : null, Profile::LANGS))) : [],
            'json_ld' => $indexable ? $this->jsonLd($p, $orig, $urlFor($lang), $image, $display, $lang, $export) : null,
        ];
    }

    private function description(Profile $p, string $display, string $brand, string $lang): string
    {
        $clean = fn (?string $s) => trim(preg_replace('/\s+/u', ' ', (string) $s));
        if ($bio = $clean($p->bio)) {
            return Str::limit($bio, 160, '…');
        }
        $line = implode(' · ', array_filter([$clean($p->headline), $clean($p->location)]));
        if ($line !== '') {
            return Str::limit($display.' — '.$line, 160, '…');
        }

        return $lang === 'en' ? "{$display}'s public profile on {$brand}." : "پروفایل عمومی {$display} در {$brand}.";
    }

    private function jsonLd(Profile $p, Profile $orig, string $url, string $image, string $display, string $lang, ProfileExport $export): string
    {
        $other = $lang === 'en' ? $orig->name_fa : $orig->name_en;
        $sameAs = array_values(array_unique(array_filter(array_merge(
            array_values($export->socials($p)),
            collect($export->verifiedAccounts($p))->pluck('url')->all(),
            [$p->website],
        ))));

        $person = array_filter([
            '@type' => 'Person',
            '@id' => $url.'#person',
            'name' => $display,
            'alternateName' => ($other && $other !== $display) ? $other : null,
            'url' => $url,
            'image' => $image,
            'jobTitle' => $p->headline ?: null,
            'description' => $p->bio ? Str::limit(trim(preg_replace('/\s+/u', ' ', $p->bio)), 300, '…') : null,
            'homeLocation' => $p->location ? ['@type' => 'Place', 'name' => $p->location] : null,
            'sameAs' => $sameAs ?: null,
        ], fn ($v) => $v !== null && $v !== '');

        $page = array_filter([
            '@context' => 'https://schema.org',
            '@type' => 'ProfilePage',
            '@id' => $url.'#page',
            'url' => $url,
            'name' => $display,
            'inLanguage' => $lang,
            'dateCreated' => $p->created_at?->toIso8601String(),
            'dateModified' => $p->updated_at?->toIso8601String(),
            'mainEntity' => $person,
        ], fn ($v) => $v !== null);

        return json_encode($page, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);
    }
}
