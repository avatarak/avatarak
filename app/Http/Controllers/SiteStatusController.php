<?php

namespace App\Http\Controllers;

use App\Models\Announcement;
use Illuminate\Http\JsonResponse;

/** What every visitor may know: are sign-ups open, and is there a notice to show. Nothing about users. */
class SiteStatusController extends Controller
{
    public function show(): JsonResponse
    {
        $locale = app()->getLocale();

        return response()->json([
            'registration_open' => (bool) config('app.registration_open'),
            'announcements' => Announcement::current()->orderByDesc('id')->limit(3)->get()
                ->map(fn ($a) => ['id' => $a->id, 'message' => $a->message($locale), 'level' => $a->level, 'dismissible' => $a->dismissible])->values(),
        ])->header('Cache-Control', 'no-cache');
    }
}
