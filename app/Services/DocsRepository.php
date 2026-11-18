<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use League\CommonMark\Extension\HeadingPermalink\HeadingPermalinkExtension;

/** Reads the Markdown docs, renders them safely and exposes ordering / neighbours. */
class DocsRepository
{
    public const LOCALES = ['fa', 'en'];

    public static function path(string $locale, string $slug): string
    {
        return resource_path("docs/{$locale}/{$slug}.md");
    }

    /** @return list<string> all slugs in navigation order */
    public function slugs(): array
    {
        return collect(config('docs.groups'))->pluck('slugs')->flatten()->all();
    }

    public function exists(string $slug): bool
    {
        return in_array($slug, $this->slugs(), true);
    }

    /**
     * Navigation tree for a locale: [{title, pages:[{slug,title,description}]}]
     */
    public function tree(string $locale): array
    {
        return collect(config('docs.groups'))->map(fn ($g) => [
            'title' => $g['title'][$locale] ?? $g['title']['en'],
            'pages' => collect($g['slugs'])->map(fn ($slug) => $this->meta($slug, $locale))->filter()->values()->all(),
        ])->all();
    }

    /** @return array{slug:string,title:string,description:string,locale:string}|null */
    public function meta(string $slug, string $locale): ?array
    {
        [$file, $used] = $this->resolve($slug, $locale);
        if (! $file) {
            return null;
        }
        [$fm] = $this->parse(file_get_contents($file));

        return ['slug' => $slug, 'title' => $fm['title'] ?? $slug, 'description' => $fm['description'] ?? '', 'locale' => $used];
    }

    /** @return array{slug:string,title:string,description:string,locale:string,html:string,group:string}|null */
    public function page(string $slug, string $locale): ?array
    {
        if (! $this->exists($slug)) {
            return null;
        }
        [$file, $used] = $this->resolve($slug, $locale);
        if (! $file) {
            return null;
        }

        $origin = rtrim(url('/'), '/');
        $key = 'docs:v3:'.md5($file.$origin.filemtime($file).config('brand.name'));
        $parsed = Cache::remember($key, 3600, function () use ($file, $origin) {
            [$fm, $body] = $this->parse(file_get_contents($file));
            $body = str_replace(['{{origin}}', '{{brand}}'], [$origin, config('brand.name_en') ?: config('brand.name')], $body);

            return ['fm' => $fm, 'html' => $this->render($body)];
        });

        $group = collect($this->tree($locale))->first(fn ($g) => collect($g['pages'])->contains('slug', $slug));

        return [
            'slug' => $slug, 'locale' => $used, 'title' => $parsed['fm']['title'] ?? $slug,
            'description' => $parsed['fm']['description'] ?? '', 'html' => $parsed['html'], 'group' => $group['title'] ?? '',
        ];
    }

    /** @return array{0:?array,1:?array} previous and next page meta */
    public function neighbours(string $slug, string $locale): array
    {
        $slugs = $this->slugs();
        $i = array_search($slug, $slugs, true);
        if ($i === false) {
            return [null, null];
        }

        return [
            $i > 0 ? $this->meta($slugs[$i - 1], $locale) : null,
            $i < count($slugs) - 1 ? $this->meta($slugs[$i + 1], $locale) : null,
        ];
    }

    /** Plain-text bundle of the English docs, for AI assistants (/docs/llms.txt). */
    public function llms(): string
    {
        $origin = rtrim(url('/'), '/');
        $brand = config('brand.name_en') ?: config('brand.name');
        $out = ["# {$brand} developer documentation", '', "> Turn an email address into an avatar and a public profile. Docs: {$origin}/docs", ''];
        foreach ($this->slugs() as $slug) {
            $file = self::path('en', $slug);
            if (! is_file($file)) {
                continue;
            }
            [$fm, $body] = $this->parse(file_get_contents($file));
            $out[] = '---';
            $out[] = '';
            $out[] = '# '.($fm['title'] ?? $slug).'  ('.$origin.'/docs/'.$slug.')';
            $out[] = '';
            $out[] = trim(str_replace(['{{origin}}', '{{brand}}'], [$origin, $brand], $body));
            $out[] = '';
        }

        return implode("\n", $out)."\n";
    }

    /** @return array{0:?string,1:string} [file, locale actually used] (falls back to the other language) */
    private function resolve(string $slug, string $locale): array
    {
        foreach ([$locale, $locale === 'fa' ? 'en' : 'fa'] as $l) {
            $f = self::path($l, $slug);
            if (is_file($f)) {
                return [$f, $l];
            }
        }

        return [null, $locale];
    }

    /** @return array{0:array<string,string>,1:string} [front matter, body] */
    public function parse(string $raw): array
    {
        $raw = str_replace(['{{origin}}', '{{brand}}'], [rtrim(url('/'), '/'), config('brand.name_en') ?: config('brand.name')], $raw);
        $fm = [];
        if (preg_match('/\A---\R(.*?)\R---\R?(.*)\z/su', $raw, $m)) {
            foreach (preg_split('/\R/u', $m[1]) as $line) {
                if (preg_match('/^([a-z_]+):[ \t]*(.*)$/u', $line, $kv)) {
                    $fm[$kv[1]] = trim($kv[2], " \t\"'");
                }
            }
            $raw = $m[2];
        }

        return [$fm, $raw];
    }

    /** Raw HTML in the source is stripped and unsafe links are dropped, even though the source is ours. */
    public function render(string $markdown): string
    {
        return Str::markdown($markdown, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
            'heading_permalink' => ['html_class' => 'doc-anchor', 'symbol' => '#', 'insert' => 'after', 'aria_hidden' => true, 'title' => '', 'id_prefix' => '', 'fragment_prefix' => '', 'apply_id_to_heading' => true],
        ], [new HeadingPermalinkExtension]);
    }
}
