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

    /** The site's own default language is served at the plain address; the other one at ?lang= (docs, legal) or /xx (home). */
    public static function defaultLang(): string
    {
        return config('brand.default_locale') === 'en' ? 'en' : 'fa';
    }

    public static function langUrl(string $path, string $lang): string
    {
        $url = url($path);

        return $lang === self::defaultLang() ? $url : $url.'?lang='.$lang;
    }

    /** Alternates for a page that exists in the given languages (+ x-default = the site's default language). */
    public static function alternates(string $path, array $langs): array
    {
        if (count($langs) < 2) {
            return [];
        }
        $out = [];
        foreach ($langs as $l) {
            $out[$l] = self::langUrl($path, $l);
        }
        $out['x-default'] = url($path);

        return $out;
    }

    /**
     * Head data for a documentation page.
     * @return array<string,mixed>
     */
    public function docs(?string $slug, ?array $page, string $locale, string $brand): array
    {
        $docs = app(DocsRepository::class);
        $served = $page['locale'] ?? $locale;                      // an untranslated page shows the other language
        $path = $slug && $slug !== 'api-explorer' ? "/docs/{$slug}" : ($slug === 'api-explorer' ? '/docs/api-explorer' : '/docs');
        $langs = $slug && $slug !== 'api-explorer'
            ? array_values(array_filter(DocsRepository::LOCALES, fn ($l) => ($docs->page($slug, $l)['locale'] ?? null) === $l))
            : DocsRepository::LOCALES;
        $isFa = $served === 'fa';
        $title = ($page['title'] ?? ($slug === 'api-explorer' ? ($isFa ? 'آزمایش API' : 'API explorer') : ($isFa ? 'مستندات' : 'Documentation'))).' · '.($isFa ? 'مستندات' : 'Docs').' · '.$brand;
        $desc = $page['description'] ?? ($isFa ? 'مستندات توسعه‌دهنده‌ها: آواتار، REST API و داده‌ی عمومی پروفایل.' : 'Developer documentation: avatars, the REST API and public profile data.');
        $url = self::langUrl($path, $served);

        $crumbs = [['Home' => url('/')], [$isFa ? 'مستندات' : 'Docs' => url('/docs')]];
        $items = [['@type' => 'ListItem', 'position' => 1, 'name' => $isFa ? 'خانه' : 'Home', 'item' => url('/')], ['@type' => 'ListItem', 'position' => 2, 'name' => $isFa ? 'مستندات' : 'Docs', 'item' => url('/docs')]];
        if ($page) {
            $items[] = ['@type' => 'ListItem', 'position' => 3, 'name' => $page['title'], 'item' => $url];
        }
        $file = $slug && $slug !== 'api-explorer' ? DocsRepository::path($served, $slug) : null;
        $article = $page ? array_filter([
            '@type' => 'TechArticle', '@id' => $url.'#article', 'headline' => $page['title'], 'description' => $page['description'] ?: null,
            'inLanguage' => $served, 'url' => $url, 'mainEntityOfPage' => $url,
            'dateModified' => $file && is_file($file) ? date('c', filemtime($file)) : null,
            'publisher' => ['@type' => 'Organization', 'name' => $brand, 'url' => url('/')],
            'isPartOf' => ['@type' => 'WebSite', 'name' => $brand, 'url' => url('/')],
        ]) : ['@type' => 'CollectionPage', '@id' => $url.'#page', 'name' => $title, 'description' => $desc, 'inLanguage' => $served, 'url' => $url];

        return $this->pageHead([
            'title' => $title, 'description' => $desc, 'canonical' => $url, 'alternates' => self::alternates($path, $langs), 'type' => $page ? 'article' : 'website',
            'locale' => $served, 'brand' => $brand,
            'graph' => [$article, ['@type' => 'BreadcrumbList', 'itemListElement' => $items]],
        ]);
    }

    /** @return array<string,mixed> */
    public function legal(string $page, string $title, string $description, string $locale, string $brand): array
    {
        $path = '/'.$page;
        $url = self::langUrl($path, $locale);

        return $this->pageHead([
            'title' => $title.' · '.$brand, 'description' => $description, 'canonical' => $url, 'alternates' => self::alternates($path, DocsRepository::LOCALES), 'type' => 'website',
            'locale' => $locale, 'brand' => $brand,
            'graph' => [
                ['@type' => 'WebPage', '@id' => $url.'#page', 'name' => $title, 'description' => $description ?: null, 'inLanguage' => $locale, 'url' => $url, 'dateModified' => config('legal.updated'),
                    'isPartOf' => ['@type' => 'WebSite', 'name' => $brand, 'url' => url('/')]],
                ['@type' => 'BreadcrumbList', 'itemListElement' => [
                    ['@type' => 'ListItem', 'position' => 1, 'name' => $locale === 'fa' ? 'خانه' : 'Home', 'item' => url('/')],
                    ['@type' => 'ListItem', 'position' => 2, 'name' => $title, 'item' => $url],
                ]],
            ],
        ]);
    }

    /** Fills in what every page has in common (social image, locale codes) and encodes the structured data. */
    private function pageHead(array $o): array
    {
        $o['og_locale'] = self::OG_LOCALES[$o['locale']] ?? 'fa_IR';
        $o['alt_locales'] = array_values(array_filter(array_map(fn ($l) => ($l !== $o['locale'] && isset($o['alternates'][$l])) ? self::OG_LOCALES[$l] : null, DocsRepository::LOCALES)));
        $o['image'] = url('/brand/og-default.jpg');
        $o['json_ld'] = json_encode(['@context' => 'https://schema.org', '@graph' => array_values(array_filter($o['graph']))], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS);

        return $o;
    }
}
