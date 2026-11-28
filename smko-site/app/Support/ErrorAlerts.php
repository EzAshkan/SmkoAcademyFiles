<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Throwable;

/** Sends unexpected server errors (the "Something went wrong" page) to admins on Telegram. */
class ErrorAlerts
{
    public static function report(Throwable $e): void
    {
        if (! app()->isProduction() || ! config('smko.telegram.relay_url')) {
            return;
        }
        // At most one alert per kind of error every 10 minutes.
        $key = 'error-alert:'.md5(get_class($e).'|'.$e->getFile().'|'.$e->getLine());
        if (! Cache::add($key, 1, now()->addMinutes(10))) {
            return;
        }

        try {
            $url = request()?->fullUrl();
            $text = "🔴 ".config('app.name')." — server error\n\n"
                .class_basename($e).': '.mb_substr($e->getMessage(), 0, 300)."\n"
                .str_replace(base_path().'/', '', $e->getFile()).':'.$e->getLine()
                .($url ? "\n\nPage: ".$url : '');
            $messages = User::where('is_admin', true)->get()
                ->filter(fn (User $u) => $u->telegramChatId())
                ->map(fn (User $u) => ['chat_id' => $u->telegramChatId(), 'text' => $text])->values()->all();
            TelegramRelay::send($messages, 'error', class_basename($e));
        } catch (Throwable) {
            // never let alerting itself break error handling
        }
    }
}
