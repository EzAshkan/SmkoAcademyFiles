<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\User;
use App\Support\FolderTree;
use App\Support\TelegramRelay;
use Illuminate\Console\Command;

/**
 * Every few minutes: tells students on Telegram about files added since the last run,
 * one message per student listing only the files they can open.
 * Files wait a few minutes first so a batch upload becomes one message.
 */
class NotifyNewFiles extends Command
{
    protected $signature = 'smko:notify-new-files {--wait=5 : Minutes a new file waits before being announced}';

    protected $description = 'Send Telegram alerts about newly added files';

    public function handle(): int
    {
        $pending = Asset::query()
            ->whereNull('notified_at')
            ->where('created_at', '<=', now()->subMinutes((int) $this->option('wait')))
            ->orderBy('folder_id')->orderBy('sort_order')
            ->get();

        if ($pending->isEmpty()) {
            return self::SUCCESS;
        }

        // Files marked "don't notify" (or with no file yet) are simply closed off.
        $announce = $pending->filter(fn (Asset $a) => $a->notify && ($a->hasFile() || $a->type === 'TEXT'));

        $messages = [];
        if ($announce->isNotEmpty()) {
            $students = User::where('is_admin', false)->where('telegram_notifications', true)->get()
                ->filter(fn (User $u) => $u->telegramChatId());

            foreach ($students as $u) {
                $mine = $announce->filter(fn (Asset $a) => FolderTree::canView($u, $a->folder_id));
                if ($mine->isEmpty()) {
                    continue;
                }
                $byFolder = $mine->groupBy('folder_id');
                $parts = [];
                foreach ($byFolder->take(6) as $folderId => $files) {
                    $parts[] = '📁 '.FolderTree::path($folderId, ' › ')."\n".TelegramRelay::bulletList($files->pluck('name')->all(), 8);
                }
                if ($byFolder->count() > 6) {
                    $parts[] = '… and files in '.($byFolder->count() - 6).' more folders';
                }
                $single = $byFolder->count() === 1;
                $messages[] = [
                    'chat_id' => $u->telegramChatId(),
                    'text' => '📚 New on SMKO Files ('.$mine->count().' '.str('file')->plural($mine->count()).")\n\n".implode("\n\n", $parts),
                    'button_text' => 'Open SMKO Files',
                    'button_url' => $single ? route('library.folder', $byFolder->keys()->first()) : route('library.home'),
                ];
            }
        }

        $queued = TelegramRelay::send($messages, 'new_files', $announce->count().' new '.str('file')->plural($announce->count()));
        Asset::whereIn('id', $pending->pluck('id'))->update(['notified_at' => now()]);
        $this->info($pending->count().' new files · '.count($messages).' students to notify · '.$queued.' queued on the bot.');

        return self::SUCCESS;
    }
}
