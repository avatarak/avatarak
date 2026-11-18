<?php

namespace App\Http\Controllers;

use App\Models\Profile;
use App\Services\DocsRepository;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;

/** robots.txt and the sitemaps. Only pages that are meant to be found are listed. */
class SeoFilesController extends Controller
{
    private const PER_FILE = 5000;

    public function robots(): Response
    {
        $lines = [
            'User-agent: *',
            'Disallow: /panel',
            'Disallow: /admin',
            'Disallow: /account',
            'Disallow: /api/',
            'Disallow: /_internal',
            'Disallow: /*/print',
            'Disallow: /*/share.svg',
            '',
            'Sitemap: '.url('/sitemap.xml'),
        ];

        return response(implode("\n", $lines)."\n", 200, ['Content-Type' => 'text/plain; charset=utf-8', 'Cache-Control' => 'public, max-age=3600']);
    }

    /** A profile is listed when its owner switched indexing on, it is visible, and it says something about the person. */
    private function listable()
    {
        return Profile::visible()->where('allow_indexing', true)
            ->where(fn ($q) => $q->whereNotNull('name_fa')->where('name_fa', '!=', '')->orWhere(fn ($w) => $w->whereNotNull('name_en')->where('name_en', '!=', '')))
            ->where(fn ($q) => $q->where(fn ($w) => $w->whereNotNull('bio')->where('bio', '!=', ''))->orWhere(fn ($w) => $w->whereNotNull('headline')->where('headline', '!=', ''))
                ->orWhere(fn ($w) => $w->whereNotNull('bio_alt')->where('bio_alt', '!=', ''))->orWhere(fn ($w) => $w->whereNotNull('headline_alt')->where('headline_alt', '!=', '')));
    }

    public function index(): Response
    {
        $xml = Cache::remember('sitemap:index', 600, function () {
            $files = [url('/sitemap-pages.xml')];
            $count = $this->listable()->count();
            for ($i = 1; $i <= (int) ceil($count / self::PER_FILE); $i++) {
                $files[] = url("/sitemap-profiles-{$i}.xml");
            }

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                .collect($files)->map(fn ($f) => '<sitemap><loc>'.e($f).'</loc></sitemap>')->implode('')
                .'</sitemapindex>'."\n";
        });

        return $this->xml($xml);
    }

    public function pages(DocsRepository $docs): Response
    {
        $xml = Cache::remember('sitemap:pages', 600, function () use ($docs) {
            $paths = array_merge(['/', '/docs', '/docs/api-explorer', '/terms', '/privacy'], array_map(fn ($s) => "/docs/{$s}", $docs->slugs()));

            return '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
                .collect($paths)->map(fn ($p) => '<url><loc>'.e(url($p)).'</loc></url>')->implode('')
                .'</urlset>'."\n";
        });

        return $this->xml($xml);
    }

    public function profiles(int $page): Response
    {
        abort_if($page < 1, 404);
        $xml = Cache::remember("sitemap:profiles:{$page}", 600, function () use ($page) {
            $rows = $this->listable()->orderBy('id')->forPage($page, self::PER_FILE)->get();
            abort_if($rows->isEmpty() && $page > 1, 404);

            $out = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
            foreach ($rows as $p) {
                $loc = route('profile.show', $p->username);
                $out .= '<url><loc>'.e($loc).'</loc><lastmod>'.$p->updated_at?->toAtomString().'</lastmod>';
                if ($p->languages === 'both') {
                    foreach (Profile::LANGS as $l) {
                        $out .= '<xhtml:link rel="alternate" hreflang="'.$l.'" href="'.e($l === $p->defaultLang() ? $loc : $loc.'?lang='.$l).'"/>';
                    }
                    $out .= '<xhtml:link rel="alternate" hreflang="x-default" href="'.e($loc).'"/>';
                }
                $out .= '</url>';
            }

            return $out.'</urlset>'."\n";
        });

        return $this->xml($xml);
    }

    private function xml(string $body): Response
    {
        return response($body, 200, ['Content-Type' => 'application/xml; charset=utf-8', 'Cache-Control' => 'public, max-age=600']);
    }
}
