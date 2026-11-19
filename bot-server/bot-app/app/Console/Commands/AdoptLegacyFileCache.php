<?php

namespace App\Console\Commands;

use App\Support\TelegramFileCache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/**
 * Run ONCE on switch-over day, before the new website goes live.
 *
 * The old website versioned files with a timestamp; the new one uses a different version
 * string. This copies every Telegram copy the bot already has into a "legacy" slot per file
 * (kept forever), so the first request for each file on the new website reuses it instead of
 * uploading the file to Telegram again.
 */
class AdoptLegacyFileCache extends Command
{
    protected $signature = 'telegram:adopt-legacy-cache {--dry-run : Only count}';

    protected $description = 'Keep the Telegram copies made for the old website when switching to the new one';

    public function handle(): int
    {
        $store = Cache::getStore();
        if (! method_exists($store, 'connection')) {
            $this->error('This needs the Redis cache store (CACHE_STORE=redis).');

            return self::FAILURE;
        }
        $redis = $store->connection();
        $prefix = $store->getPrefix();

        // Newest copy per file (old versions are timestamps, so the biggest is the latest).
        $latest = [];
        $cursor = null;
        $connPrefix = (string) ($redis->client()->getOption(\Redis::OPT_PREFIX) ?? '');
        do {
            [$cursor, $keys] = $redis->scan($cursor ?? 0, ['match' => $connPrefix.$prefix.'tg_file_id:*', 'count' => 1000]) ?: [0, []];
            foreach ($keys as $raw) {
                $key = substr($raw, strlen($connPrefix.$prefix));
                if (preg_match('/^tg_file_id:(\d+):(.+)$/', $key, $m)) {
                    if (! isset($latest[$m[1]]) || strcmp($m[2], $latest[$m[1]][0]) > 0) {
                        $latest[$m[1]] = [$m[2], $key];
                    }
                }
            }
        } while ($cursor);

        $adopted = 0;
        foreach ($latest as $assetId => [, $key]) {
            $fileId = Cache::get($key);
            if (! $fileId) {
                continue;
            }
            if (! $this->option('dry-run')) {
                Cache::forever(TelegramFileCache::legacyKey($assetId), $fileId);
            }
            $adopted++;
        }

        $this->info(($this->option('dry-run') ? 'Would keep ' : 'Kept ')."{$adopted} Telegram copies for the new website.");

        return self::SUCCESS;
    }
}
