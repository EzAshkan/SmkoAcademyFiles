<?php

namespace App\Http\Controllers;

use App\Models\TelegramClaim;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Called by the Telegram bot (bot-server/bot-app WebhookController):
 *   POST /internal/telegram/claim   Authorization: Bearer <APP_INTERNAL_TOKEN>   {"token": "..."}
 * 200 → {asset_id, hash, filename, download_url}; 404 → invalid / expired / used.
 */
class TelegramClaimController extends Controller
{
    public function __invoke(Request $request)
    {
        $expected = (string) config('smko.telegram.internal_token');
        if ($expected === '' || ! hash_equals($expected, (string) $request->bearerToken())) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $token = (string) $request->input('token');
        if (! preg_match('/^[A-Za-z0-9_-]{32}$/', $token)) {
            return response()->json(['message' => 'Not found'], 404);
        }

        // Mark used atomically so a code can only ever be claimed once.
        $claimed = DB::table('telegram_claims')
            ->where('token', $token)
            ->whereNull('used_at')
            ->where('expires_at', '>', now())
            ->update(['used_at' => now(), 'updated_at' => now()]);

        $claim = $claimed ? TelegramClaim::with('asset')->find($token) : null;
        if (! $claim || ! $claim->asset || ! $claim->asset->hasFile()) {
            return response()->json(['message' => 'Not found'], 404);
        }

        $asset = $claim->asset;

        return response()->json([
            'asset_id' => $asset->id,
            'hash' => $asset->versionHash(),
            'filename' => $asset->downloadName(),
            'download_url' => $asset->temporaryUrl(config('smko.telegram.bot_download_minutes'), asAttachment: false),
        ]);
    }
}
