<?php

namespace App\Http\Controllers;

use App\Services\DocsRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** /terms and /privacy: Markdown in resources/legal/{fa,en}/, rendered with the operator's details filled in. */
class LegalController extends Controller
{
    public const PAGES = ['terms', 'privacy'];

    public function show(Request $request, DocsRepository $docs, string $page): Response
    {
        abort_unless(in_array($page, self::PAGES, true), 404);

        $lang = $request->query('lang');
        if (in_array($lang, DocsRepository::LOCALES, true)) {
            app()->setLocale($lang);
        }
        $locale = app()->getLocale() === 'en' ? 'en' : 'fa';

        $file = resource_path("legal/$locale/$page.md");
        abort_unless(is_file($file), 404);
        [$fm, $body] = $docs->parse(file_get_contents($file));
        $tokens = ['{{entity}}' => e(config('legal.entity')), '{{contact}}' => '['.e(config('legal.contact_email')).'](mailto:'.rawurlencode(config('legal.contact_email')).')'];
        $fill = fn (string $s) => strtr($s, $tokens);

        $res = response()->view('legal.page', [
            'title' => $fill($fm['title'] ?? $page), 'description' => $fill($fm['description'] ?? ''), 'html' => $docs->render($fill($body)),
            'locale' => $locale, 'rtl' => $locale === 'fa', 'page' => $page, 'updated' => config('legal.updated'),
            'brand' => $locale === 'fa' ? config('brand.name') : (config('brand.name_en') ?: config('brand.name')),
        ]);

        if ($lang && in_array($lang, DocsRepository::LOCALES, true)) {
            $res->withCookie(cookie('locale', $lang, 60 * 24 * 365, '/', null, false, false));
        }

        return $res->header('Content-Language', $locale)->header('Cache-Control', 'public, max-age=300');
    }
}
