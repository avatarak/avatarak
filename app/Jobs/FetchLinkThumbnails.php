<?php

namespace App\Jobs;

use App\Models\Profile;
use App\Services\LinkPreviewService;
use Illuminate\Foundation\Bus\Dispatchable;

/**
 * Runs after the HTTP response (see dispatchAfterResponse), so it needs no queue worker and never
 * delays the user's save. Link entries get `thumb` = key on success, false when the site has no image.
 */
class FetchLinkThumbnails
{
    use Dispatchable;

    public function __construct(public int $profileId) {}

    public function handle(LinkPreviewService $previews): void
    {
        $profile = Profile::find($this->profileId);
        if (! $profile || empty($profile->links)) {
            return;
        }

        $results = [];
        foreach ($profile->links as $link) {
            if (! array_key_exists('thumb', $link) && ! isset($results[$link['url']])) {
                $results[$link['url']] = $previews->fetch($link['url']) ? LinkPreviewService::key($link['url']) : false;
            }
        }
        if (! $results) {
            return;
        }

        // Re-read: the user may have edited links while we were fetching; only fill what is still pending.
        $fresh = Profile::find($this->profileId);
        $links = collect($fresh->links ?? [])->map(function ($l) use ($results) {
            if (! array_key_exists('thumb', $l) && array_key_exists($l['url'], $results)) {
                $l['thumb'] = $results[$l['url']];
            }

            return $l;
        })->all();
        $fresh->forceFill(['links' => $links])->saveQuietly();
    }
}
