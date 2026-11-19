<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Jobs\DeliverTelegramAsset;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;

class WebhookController extends Controller
{
    public function __invoke(Request $request, Api $telegram)
    {
        $update = $telegram->getWebhookUpdate();

        $updateId = $update->getUpdateId();

        // Prevent duplicate processing of the same Telegram update.
        if ($updateId && !Cache::add("tg_update:{$updateId}", 1, now()->addDay())) {
            return response()->json(['ok' => true]);
        }

        $message = $update->getMessage();

        if (!$message || !$message->getText()) {
            return response()->json(['ok' => true]);
        }

        $text = trim($message->getText());

        if (!preg_match('/^\/start\s+([A-Za-z0-9_-]{32})$/u', $text, $matches)) {
            $telegram->sendMessage([
                'chat_id' => $message->getChat()->getId(),
                'text' => 'Please use the download button from the website.',
            ]);

            return response()->json(['ok' => true]);
        }

        $token = $matches[1];
        $chatId = $message->getChat()->getId();

        try {
            $response = Http::withToken(config('services.app_internal.token'))
                ->timeout(10)
                ->post(config('services.app_internal.base_url') . '/internal/telegram/claim', [
                    'token' => $token,
                ]);
        } catch (ConnectionException $e) {
            Log::error('Cannot reach Laravel internal API', [
                'error' => $e->getMessage(),
            ]);

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ Service is temporarily unavailable. Please try again later.',
            ]);

            return response()->json(['ok' => true]);
        }

        if ($response->status() === 404) {
            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '⚠️ This download link is invalid, expired, or already used.',
            ]);

            return response()->json(['ok' => true]);
        }

        if ($response->failed()) {
            Log::error('Laravel internal claim failed', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $telegram->sendMessage([
                'chat_id' => $chatId,
                'text' => '❌ Could not validate your download link. Please try again.',
            ]);

            return response()->json(['ok' => true]);
        }

        $delivery = $response->json();

        $telegram->sendMessage([
            'chat_id' => $chatId,
            'text' => '⏳ Preparing your file...',
        ]);

        DeliverTelegramAsset::dispatch($chatId, $delivery);

        return response()->json(['ok' => true]);
    }
}
