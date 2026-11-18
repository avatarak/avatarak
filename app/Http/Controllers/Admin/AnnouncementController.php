<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Announcement;
use App\Models\AuditLog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AnnouncementController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => Announcement::orderByDesc('id')->get()->map(fn ($a) => $this->present($a))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $a = Announcement::create($this->data($request) + ['created_by' => $request->user()->id]);
        AuditLog::record('announcement.create', $a);

        return response()->json($this->present($a), 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $a = Announcement::findOrFail($id);
        $a->update($this->data($request));
        AuditLog::record('announcement.update', $a);

        return response()->json($this->present($a->refresh()));
    }

    public function destroy(int $id): JsonResponse
    {
        $a = Announcement::findOrFail($id);
        AuditLog::record('announcement.delete', $a);
        $a->delete();

        return response()->json(['message' => 'ok']);
    }

    private function data(Request $request): array
    {
        return $request->validate([
            'message_fa' => ['required', 'string', 'max:500'],
            'message_en' => ['nullable', 'string', 'max:500'],
            'level' => ['required', Rule::in(Announcement::LEVELS)],
            'active' => ['required', 'boolean'],
            'dismissible' => ['required', 'boolean'],
            'starts_at' => ['nullable', 'date'],
            'ends_at' => ['nullable', 'date', 'after:starts_at'],
        ]);
    }

    private function present(Announcement $a): array
    {
        return $a->only(['id', 'message_fa', 'message_en', 'level', 'active', 'dismissible']) + [
            'starts_at' => $a->starts_at?->toIso8601String(), 'ends_at' => $a->ends_at?->toIso8601String(),
            'live' => $a->active && (! $a->starts_at || $a->starts_at->isPast()) && (! $a->ends_at || $a->ends_at->isFuture()),
        ];
    }
}
