<?php

namespace App\Services;

use App\Models\Avatar;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** Removes an account and every file that belongs to it. Rows follow through ON DELETE CASCADE. */
class UserDeletion
{
    public function delete(User $user): void
    {
        $disk = Storage::disk(config('avatar.disk'));
        $paths = Avatar::where('user_id', $user->id)->pluck('path')->all();

        $profile = $user->profile;
        foreach ((array) ($profile?->links ?? []) as $link) {
            if (! empty($link['thumb']) && preg_match('/^[a-f0-9]{40}$/', $link['thumb'])) {
                $paths[] = LinkPreviewService::path($link['thumb']);
            }
        }
        if ($profile?->avatar_path) {
            $paths[] = $profile->avatar_path;
        }

        DB::transaction(function () use ($user) {
            $user->delete();
        });

        // files go after the rows, so a failure never leaves a half-deleted account
        foreach (array_unique($paths) as $p) {
            try {
                $disk->delete($p);
            } catch (\Throwable $e) {
                report($e);
            }
        }
        try {
            $disk->deleteDirectory("avatars/{$user->id}");
        } catch (\Throwable $e) {
            report($e);
        }
    }
}
