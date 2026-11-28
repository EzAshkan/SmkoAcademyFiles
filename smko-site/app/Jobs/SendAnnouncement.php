<?php

namespace App\Jobs;

use App\Models\Announcement;
use App\Support\TelegramRelay;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

class SendAnnouncement implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $announcementId)
    {
    }

    public function handle(): void
    {
        $a = Announcement::find($this->announcementId);
        if (! $a || $a->telegram_sent_at) {
            return; // deleted, or already sent
        }

        $icon = ['info' => '📢', 'success' => '✅', 'warning' => '⚠️', 'danger' => '🚨'][$a->style] ?? '📢';
        $text = $icon.' '.$a->title.($a->body ? "\n\n".$a->body : '');
        $messages = $a->audience()
            ->filter(fn ($u) => $u->telegramChatId())
            ->map(fn ($u) => ['chat_id' => $u->telegramChatId(), 'text' => $text, 'button_text' => 'Open SMKO Files', 'button_url' => route('library.home')])
            ->values()->all();

        $queued = TelegramRelay::send($messages, 'announcement', $a->title);
        $a->forceFill(['telegram_sent_at' => now(), 'telegram_sent_count' => $queued ?: count($messages)])->save();
    }
}
