<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Services\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index(Settings $settings): JsonResponse
    {
        return response()->json(['settings' => $settings->all()]);
    }

    public function update(Request $request, Settings $settings, string $key): JsonResponse
    {
        $request->validate(['value' => ['present']]);
        $before = collect($settings->all())->firstWhere('key', $key) ?? abort(404);
        $settings->set($key, $request->input('value'));
        AuditLog::record('settings.update', null, ['key' => $key, 'from' => $before['value'], 'to' => $settings->clean($key, $request->input('value'))]);

        return $this->index($settings);
    }

    public function reset(Settings $settings, string $key): JsonResponse
    {
        $before = collect($settings->all())->firstWhere('key', $key) ?? abort(404);
        $settings->reset($key);
        AuditLog::record('settings.reset', null, ['key' => $key, 'from' => $before['value']]);

        return $this->index($settings);
    }
}
