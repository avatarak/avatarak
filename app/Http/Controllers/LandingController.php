<?php

namespace App\Http\Controllers;

/** The single-page app shell for the home page, rendered (head and text for crawlers) in the language of its address. */
class LandingController extends Controller
{
    public static function render(string $lang)
    {
        request()->attributes->set('landing', $lang);
        app()->setLocale($lang);

        return view('app');
    }
}
