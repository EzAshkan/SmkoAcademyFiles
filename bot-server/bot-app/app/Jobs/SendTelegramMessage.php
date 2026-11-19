<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramResponseException;
use Telegram\Bot\Keyboard\Keyboard;

class SendTelegramMessage implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 3;

    public array $backoff = [5, 30, 120];

    public function __construct(public array $message)
    {
    }

    public function handle(Api $telegram): void
    {
        $params = [
            'chat_id' => $this->message['chat_id'],
            'text' => $this->message['text'],
            'disable_web_page_preview' => true,
        ];
        if (! empty($this->message['button_url'])) {
            $params['reply_markup'] = Keyboard::make()->inline()->row([
                Keyboard::inlineButton(['text' => $this->message['button_text'] ?? 'Open', 'url' => $this->message['button_url']]),
            ]);
        }

        try {
            $telegram->sendMessage($params);
        } catch (TelegramResponseException $e) {
            // Blocked the bot / never started it / deleted account: don't retry.
            if (in_array($e->getCode(), [400, 403], true)) {
                Log::info('Telegram message not delivered', ['chat_id' => $this->message['chat_id'], 'error' => $e->getMessage()]);

                return;
            }
            throw $e;
        }
    }
}
