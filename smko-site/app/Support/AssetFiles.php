<?php

namespace App\Support;

use App\Models\Asset;
use Illuminate\Support\Facades\Storage;

/**
 * Moves an uploaded file from the local temp disk into the bucket as {prefix}/{id}.{ext}
 * (the same layout the old site used) and records its extension, size and fingerprint.
 * If the exact same file is already in the bucket, it's reused instead of stored twice.
 */
class AssetFiles
{
    public const TEMP_DISK = 'local';

    public const TEMP_DIR = 'upload-tmp';

    /** @return bool true when an identical file already in the bucket was reused (nothing uploaded) */
    public static function attach(Asset $asset, ?string $tempPath, ?string $originalName = null): bool
    {
        $temp = Storage::disk(self::TEMP_DISK);
        if (! $tempPath || ! $temp->exists($tempPath)) {
            return false;
        }

        $ext = static::cleanExtension($originalName ?: $tempPath);
        $size = $temp->size($tempPath);
        $md5 = md5_file($temp->path($tempPath));
        $bucket = Storage::disk(config('smko.disk'));
        $oldKey = $asset->hasFile() ? $asset->storageKey() : null;

        // Same content already stored? Point at it instead of uploading again.
        $existing = static::findByContent($md5, $size, $asset->id);
        if ($existing) {
            $newKey = $existing->storageKey();
        } else {
            $newKey = config('smko.prefix')."/{$asset->id}.{$ext}";
            // A replacement gets its own name (so the Telegram bot sees a new version), and never
            // overwrites an object that copies still use.
            if ($newKey === $oldKey || LibraryOps::keyIsShared($newKey, $asset->id)) {
                $newKey = config('smko.prefix')."/{$asset->id}-".now()->format('YmdHis').".{$ext}";
            }
            $stream = $temp->readStream($tempPath);
            $bucket->writeStream($newKey, $stream);
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        // The file was replaced: remove the old object unless something else still uses it.
        if ($oldKey && $oldKey !== $newKey && ! LibraryOps::keyIsShared($oldKey, $asset->id)) {
            $bucket->delete($oldKey);
        }

        $asset->forceFill([
            'file_ext' => $existing?->file_ext ?? $ext,
            'storage_key' => $newKey === config('smko.prefix')."/{$asset->id}.{$ext}" ? null : $newKey,
            'size_bytes' => $size,
            'size_label' => null,
            'content_md5' => $md5,
        ])->save();

        $temp->delete($tempPath);

        return (bool) $existing;
    }

    /** An existing file with exactly this content (MD5 + size when known). */
    public static function findByContent(string $md5, ?int $size = null, ?int $exceptId = null): ?Asset
    {
        return Asset::query()
            ->where('content_md5', $md5)
            ->whereNotNull('file_ext')
            ->when($exceptId, fn ($q) => $q->where('id', '!=', $exceptId))
            ->when($size, fn ($q) => $q->where(fn ($q) => $q->whereNull('size_bytes')->orWhere('size_bytes', $size)))
            ->orderBy('id')
            ->first();
    }

    public static function cleanExtension(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));

        return preg_replace('/[^a-z0-9]/', '', $ext) ?: 'bin';
    }

    /** "Lesson_12-Audio Track.mp3" → "Lesson 12-Audio Track" */
    public static function titleFromFilename(string $filename): string
    {
        return trim(preg_replace('/[_\s]+/', ' ', pathinfo($filename, PATHINFO_FILENAME))) ?: 'File';
    }

    public static function typeFromExtension(string $ext): string
    {
        return match (true) {
            in_array($ext, ['mp3', 'm4a', 'ogg', 'oga', 'wav', 'aac', 'flac', 'opus'], true) => 'AUDIO',
            in_array($ext, ['mp4', 'mkv', 'mov', 'webm', 'avi', 'm4v'], true) => 'VIDEO',
            in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true) => 'PHOTO',
            default => 'DOCUMENT',
        };
    }
}
