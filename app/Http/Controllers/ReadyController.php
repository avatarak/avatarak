<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** /up/ready: is the app able to do its job? 200 when the database, cache and file storage all work, else 503. */
class ReadyController extends Controller
{
    public function __invoke(): JsonResponse
    {
        $failed = [];
        $checks = [
            'database' => fn () => DB::select('select 1'),
            'cache' => function () {
                Cache::put('ready-check', '1', 10);
                if (Cache::get('ready-check') !== '1') {
                    throw new \RuntimeException('cache did not return the value');
                }
            },
            'storage' => function () {
                $disk = Storage::disk(config('avatar.disk'));
                if (! $disk->put('.ready-check', '1')) {
                    throw new \RuntimeException('storage is not writable');
                }
                $disk->delete('.ready-check');
            },
        ];

        foreach ($checks as $name => $check) {
            try {
                $check();
            } catch (\Throwable $e) {
                report($e);
                $failed[] = $name;
            }
        }

        // Names only: this URL is public, so it must not describe the failure.
        return response()->json($failed ? ['status' => 'degraded', 'failed' => $failed] : ['status' => 'ok'], $failed ? 503 : 200)
            ->header('Cache-Control', 'no-store');
    }
}
