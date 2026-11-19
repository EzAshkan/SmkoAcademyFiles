<?php

namespace App\Support;

/**
 * Where the bot remembers the Telegram copy (file_id) of each file it has sent.
 * Entries are stored without expiry (Cache::forever). Redis runs with
 * "volatile-lru", so only keys WITH an expiry can ever be evicted — these never are.
 */
class TelegramFileCache
{
    /** Copy of one version of a file. The website changes the hash when the file is replaced. */
    public static function key(int|string $assetId, string $hash): string
    {
        return "tg_file_id:{$assetId}:{$hash}";
    }

    /** Copy adopted from the old website (its version numbers differ); used once, then re-saved under key(). */
    public static function legacyKey(int|string $assetId): string
    {
        return "tg_file_legacy:{$assetId}";
    }
}
