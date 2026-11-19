<?php

namespace App\Http\Controllers;

use App\Jobs\SendTelegramMessage;
use Illuminate\Http\Request;

/**
 * Lets the SMKO Files website send Telegram messages through this server
 * (the website runs in Iran, where api.telegram.org can't be reached).
 *
 * POST /api/internal/send   Authorization: Bearer <APP_INTERNAL_TOKEN>
 * {"messages": [{"chat_id": "123", "text": "...", "button_text": "Open", "button_url": "https://..."}]}
 */
class InternalSendController extends Controller
{
    public function __invoke(Request $request)
    {
        $expected = (string) config('services.app_internal.token');
        if ($expected === '' || ! hash_equals($expected, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $data = $request->validate([
            'messages' => ['required', 'array', 'max:500'],
            'messages.*.chat_id' => ['required', 'regex:/^-?\d{3,20}$/'],
            'messages.*.text' => ['required', 'string', 'max:4096'],
            'messages.*.button_text' => ['nullable', 'string', 'max:64'],
            'messages.*.button_url' => ['nullable', 'url', 'max:500'],
        ]);

        // Spread sends out (~20 per second) to stay under Telegram's limits.
        foreach (array_values($data['messages']) as $i => $m) {
            SendTelegramMessage::dispatch($m)->delay(now()->addMilliseconds(50 * $i));
        }

        return response()->json(['queued' => count($data['messages'])]);
    }
}
