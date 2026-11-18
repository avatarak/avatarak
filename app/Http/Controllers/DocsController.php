<?php

namespace App\Http\Controllers;

use App\Services\DocsRepository;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cookie;

class DocsController extends Controller
{
    public function __construct(private DocsRepository $docs) {}

    public function index(Request $request): Response
    {
        $locale = $this->locale($request);

        return $this->respond($request, response()->view('docs.index', $this->shared($locale) + ['current' => null]), $locale);
    }

    public function show(Request $request, string $slug): Response
    {
        $locale = $this->locale($request);
        $page = $this->docs->page($slug, $locale);
        abort_unless($page, 404);
        [$prev, $next] = $this->docs->neighbours($slug, $locale);

        return $this->respond($request, response()->view('docs.page', $this->shared($locale) + [
            'current' => $slug, 'page' => $page, 'prev' => $prev, 'next' => $next,
            'editUrl' => config('docs.repo').'/edit/'.config('docs.branch').'/resources/docs/'.$page['locale'].'/'.$slug.'.md',
            'translated' => $page['locale'] === $locale,
        ]), $locale);
    }

    /** The OpenAPI 3.1 description, for code generators, Postman, Swagger UI… */
    public function openapi(\App\Services\OpenApi $spec): \Illuminate\Http\JsonResponse
    {
        return response()->json($spec->build(), 200, ['Access-Control-Allow-Origin' => '*', 'Cache-Control' => 'public, max-age=300'], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    /** Try the API from the browser: choose an operation, fill the fields, send, see the real answer. */
    public function explorer(Request $request): Response
    {
        $locale = $this->locale($request);

        return $this->respond($request, response()->view('docs.explorer', $this->shared($locale) + ['current' => 'api-explorer', 'withExplorer' => true]), $locale);
    }

    public function llms(): Response
    {
        return response($this->docs->llms(), 200, [
            'Content-Type' => 'text/plain; charset=utf-8',
            'Cache-Control' => 'public, max-age=3600',
            'Access-Control-Allow-Origin' => '*',
        ]);
    }

    /** ?lang=fa|en picks (and remembers) the language; otherwise the normal cookie/default applies. */
    private function locale(Request $request): string
    {
        $lang = $request->query('lang');
        if (is_string($lang) && in_array($lang, DocsRepository::LOCALES, true)) {
            App::setLocale($lang);
            Cookie::queue(cookie('locale', $lang, 60 * 24 * 365, '/', null, false, false));

            return $lang;
        }

        return in_array(App::getLocale(), DocsRepository::LOCALES, true) ? App::getLocale() : 'fa';
    }

    private function shared(string $locale): array
    {
        return [
            't' => $locale === 'fa' ? [
        'docs' => 'مستندات', 'search' => 'جست‌وجو در مستندات…', 'getKey' => 'دریافت کلید API', 'menu' => 'منو', 'onThisPage' => 'در این صفحه',
        'prev' => 'قبلی', 'next' => 'بعدی', 'edit' => 'ویرایش این صفحه در GitHub', 'notTranslated' => 'این صفحه هنوز به فارسی ترجمه نشده است و نسخه‌ی انگلیسی نمایش داده می‌شود.',
        'tools' => 'ابزارها', 'explorer' => 'آزمایش API', 'copy' => 'کپی', 'copied' => 'کپی شد', 'noResults' => 'نتیجه‌ای پیدا نشد.', 'home' => 'صفحه‌ی اصلی سایت', 'llms' => 'برای دستیارهای هوش مصنوعی',
    ] : [
        'docs' => 'Docs', 'search' => 'Search the docs…', 'getKey' => 'Get an API key', 'menu' => 'Menu', 'onThisPage' => 'On this page',
        'prev' => 'Previous', 'next' => 'Next', 'edit' => 'Edit this page on GitHub', 'notTranslated' => 'This page is not translated yet; showing the Persian version.',
        'tools' => 'Tools', 'explorer' => 'API explorer', 'copy' => 'Copy', 'copied' => 'Copied', 'noResults' => 'No results.', 'home' => 'Back to the site', 'llms' => 'For AI assistants',
    ],
            'locale' => $locale, 'rtl' => $locale === 'fa', 'tree' => $this->docs->tree($locale),
            'brand' => $locale === 'fa' ? config('brand.name') : (config('brand.name_en') ?: config('brand.name')),
            'repo' => config('docs.repo'),
        ];
    }

    private function respond(Request $request, Response $res, string $locale): Response
    {
        return $res->header('Content-Language', $locale);
    }
}
