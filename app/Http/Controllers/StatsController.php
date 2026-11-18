<?php

namespace App\Http\Controllers;

use App\Services\StatsRecorder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class StatsController extends Controller
{
    public function show(Request $request, StatsRecorder $stats): JsonResponse
    {
        $days = (int) $request->query('days', 30);

        return response()->json($stats->forUser($request->user()->id, in_array($days, [7, 30, 90], true) ? $days : 30));
    }
}
