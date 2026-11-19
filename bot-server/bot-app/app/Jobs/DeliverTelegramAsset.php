<?php

namespace App\Jobs;

use App\Support\TelegramFileCache;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Telegram\Bot\Api;
use Telegram\Bot\Exceptions\TelegramSDKException;
use Telegram\Bot\FileUpload\InputFile;
use Throwable;

class DeliverTelegramAsset implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 3;

    public array $backoff = [10, 60, 300];

    public int $timeout = 1800;

    public function __construct(
        public int $chatId,
        public array $delivery
    ) {
    }

    public function handle(Api $telegram): void
    {
        $assetId = $this->delivery['asset_id'];
        // Telegram copy of this exact version of the file (kept forever — see TelegramFileCache).
        $cacheKey = TelegramFileCache::key($assetId, $this->delivery['hash']);
        $caption = sprintf('📄 %s', $this->delivery['filename']);

        // Known copy for this version, or one adopted from the old website (first delivery after the switch).
        $fileId = Cache::get($cacheKey);
        $fromLegacy = false;
        if (! $fileId && ($fileId = Cache::get(TelegramFileCache::legacyKey($assetId)))) {
            $fromLegacy = true;
        }

        if ($fileId) {
            try {
                $telegram->sendDocument([
                    'chat_id' => $this->chatId,
                    'document' => $fileId,
                    'caption' => $caption,
                ]);

                if ($fromLegacy) {
                    Cache::forever($cacheKey, $fileId);
                    Cache::forget(TelegramFileCache::legacyKey($assetId));
                }

                return;
            } catch (TelegramSDKException $e) {
                if (! str_contains(strtolower($e->getMessage()), 'file_id')) {
                    throw $e;
                }
                // Telegram no longer knows that copy: forget it and upload again below.
                Cache::forget($fromLegacy ? TelegramFileCache::legacyKey($assetId) : $cacheKey);
            }
        }

        $response = $telegram->sendDocument([
            'chat_id' => $this->chatId,
            'document' => InputFile::create($this->delivery['download_url'], $this->delivery['filename']),
            'caption' => $caption,
        ]);

        $document = $response->getDocument();

        if ($document && $document->getFileId()) {
            // No expiry: the same file is never uploaded to Telegram twice.
            Cache::forever($cacheKey, $document->getFileId());
        }
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Telegram asset delivery failed', [
            'chat_id' => $this->chatId,
            'asset_id' => $this->delivery['asset_id'] ?? null,
            'error' => $exception->getMessage(),
        ]);

        try {
            app(Api::class)->sendMessage([
                'chat_id' => $this->chatId,
                'text' => '❌ Sorry, the file could not be delivered. Please request a new link from the website.',
            ]);
        } catch (Throwable $e) {
            Log::error('Could not send Telegram failure message', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
