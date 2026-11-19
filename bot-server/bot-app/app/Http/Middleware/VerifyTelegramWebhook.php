<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class VerifyTelegramWebhook
{
    public function handle(Request $request, Closure $next)
    {
        $secret = config('services.telegram.webhook_secret');

        $header = $request->header('X-Telegram-Bot-Api-Secret-Token');

        if (! $secret || ! hash_equals($secret, (string) $header)) {
            abort(403, 'Invalid webhook secret');
        }

        return $next($request);
    }
}
