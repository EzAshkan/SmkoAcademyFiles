<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Sends Telegram messages through the bot server (POST {relay}/api/internal/send),
 * because the site's server can't reach Telegram directly.
 * Without SMKO_TELEGRAM_RELAY_URL (local testing) messages are only written to the log.
 */
class TelegramRelay
{
    /** @var array<int, array> messages "sent" while faking (tests) */
    public static array $sent = [];

    public static bool $fake = false;

    /**
     * @param  array<int, array{chat_id:string, text:string, button_text?:string, button_url?:string}>  $messages
     * @return int number queued on the bot server
     */
    public static function send(array $messages): int
    {
        $messages = array_values(array_filter($messages, fn ($m) => ! empty($m['chat_id']) && ! empty($m['text'])));
        if (! $messages) {
            return 0;
        }
        foreach ($messages as &$m) {
            $m['text'] = mb_substr($m['text'], 0, 4000);
            // Telegram refuses buttons pointing at localhost.
            if (! empty($m['button_url']) && preg_match('~^https?://(127\.0\.0\.1|localhost)~', $m['button_url'])) {
                unset($m['button_url'], $m['button_text']);
            }
        }
        unset($m);

        if (static::$fake) {
            array_push(static::$sent, ...$messages);

            return count($messages);
        }

        $url = config('smko.telegram.relay_url');
        if (! $url) {
            Log::info('Telegram relay not configured — would send '.count($messages).' messages', ['first' => $messages[0]]);

            return 0;
        }

        $queued = 0;
        foreach (array_chunk($messages, 400) as $chunk) {
            $res = Http::withToken(config('smko.telegram.internal_token'))
                ->timeout(20)->retry(2, 2000, throw: false)
                ->post(rtrim($url, '/').'/api/internal/send', ['messages' => $chunk]);
            if (! $res->successful()) {
                throw new \RuntimeException('Telegram relay failed: HTTP '.$res->status().' '.mb_substr($res->body(), 0, 200));
            }
            $queued += (int) $res->json('queued');
        }

        return $queued;
    }

    /** Escapes nothing — messages are plain text; keeps one place to shorten long lists. */
    public static function bulletList(array $items, int $max = 15): string
    {
        $lines = array_map(fn ($i) => '• '.$i, array_slice($items, 0, $max));
        if (count($items) > $max) {
            $lines[] = '… and '.(count($items) - $max).' more';
        }

        return implode("\n", $lines);
    }
}
