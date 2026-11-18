<?php

namespace App\Http\Controllers;

use App\Models\GalleryImage;
use App\Models\Profile;
use App\Services\Gallery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GalleryController extends Controller
{
    public function index(Request $request, Gallery $gallery): JsonResponse
    {
        return response()->json($this->state($request, $gallery));
    }

    public function store(Request $request, Gallery $gallery): JsonResponse
    {
        $request->validate([
            'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240', 'dimensions:min_width=100,min_height=100,max_width=8000,max_height=8000'],
            'caption' => ['nullable', 'string', 'max:140'],
        ]);
        $image = $gallery->add($request->user(), $request->file('image'), $request->input('caption'));
        $this->touch($request);

        return response()->json($this->state($request, $gallery) + ['id' => $image->id], 201);
    }

    public function update(Request $request, Gallery $gallery, int $id): JsonResponse
    {
        $image = $request->user()->galleryImages()->findOrFail($id);
        $data = $request->validate(['caption' => ['nullable', 'string', 'max:140']]);
        $image->update(['caption' => $data['caption'] ?: null]);
        $this->touch($request);

        return response()->json($this->state($request, $gallery));
    }

    /** Body: {"order": [id, id, …]} — every id must be the caller's own. */
    public function reorder(Request $request, Gallery $gallery): JsonResponse
    {
        $data = $request->validate(['order' => ['required', 'array', 'max:100'], 'order.*' => ['integer', 'distinct']]);
        $own = $request->user()->galleryImages()->pluck('id')->all();
        abort_unless(count(array_diff($data['order'], $own)) === 0, 422);
        DB::transaction(function () use ($data) {
            foreach (array_values($data['order']) as $i => $id) {
                GalleryImage::whereKey($id)->update(['position' => $i + 1]);
            }
        });
        $this->touch($request);

        return response()->json($this->state($request, $gallery));
    }

    public function destroy(Request $request, Gallery $gallery, int $id): JsonResponse
    {
        $gallery->remove($request->user()->galleryImages()->findOrFail($id));
        $this->touch($request);

        return response()->json($this->state($request, $gallery));
    }

    /** The owner's own preview; same bytes as the public route but never publicly cached. */
    public function mine(Request $request, Gallery $gallery, int $id, string $size = 'thumb')
    {
        $image = $request->user()->galleryImages()->findOrFail($id);

        return $this->image($gallery, $image, $size, 'private, max-age=600');
    }

    /** Public photo: only for a visible profile. Same answer for "no such photo" and "hidden profile". */
    public function show(Gallery $gallery, string $username, int $id, string $size = 'full')
    {
        $profile = Profile::visible()->where('username', strtolower($username))->firstOrFail();
        $hidden = $profile->hidden_sections ?? [];
        abort_if(in_array('gallery', $hidden, true), 404);
        $image = GalleryImage::where('user_id', $profile->user_id)->findOrFail($id);

        return $this->image($gallery, $image, $size, 'public, max-age=86400');
    }

    private function image(Gallery $gallery, GalleryImage $image, string $size, string $cache)
    {
        abort_unless(in_array($size, ['thumb', 'full'], true), 404);
        $bytes = $gallery->bytes($image, $size === 'thumb');
        abort_unless($bytes, 404);

        return response($bytes, 200, ['Content-Type' => 'image/jpeg', 'Cache-Control' => $cache, 'X-Content-Type-Options' => 'nosniff']);
    }

    private function state(Request $request, Gallery $gallery): array
    {
        return [
            'limit' => $gallery->limit($request->user()),
            'images' => $request->user()->galleryImages()->get()->map(fn (GalleryImage $g) => ['id' => $g->id, 'caption' => $g->caption, 'width' => $g->width, 'height' => $g->height])->values(),
        ];
    }

    /** Photos are part of the profile: bump it and tell webhooks. */
    private function touch(Request $request): void
    {
        $profile = $request->user()->profile;
        $profile->forceFill(['updated_at' => now()])->saveQuietly();
        app(\App\Services\Webhooks::class)->dispatch($request->user(), 'profile.updated', ['changed' => ['gallery']]);
    }
}
