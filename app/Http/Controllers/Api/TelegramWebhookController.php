<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TelegramVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request, TelegramVerifier $telegram): JsonResponse
    {
        $secret = (string) config('verification.telegram.webhook_secret');

        // Not configured => the endpoint does not exist. Wrong/missing secret => forbidden, nothing processed.
        abort_unless(TelegramVerifier::enabled() && $secret !== '', 404);
        abort_unless(hash_equals($secret, (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);

        $update = $request->json()->all();
        if (is_array($update)) {
            $telegram->handleUpdate($update);
        }

        return response()->json(['ok' => true]);
    }
}
