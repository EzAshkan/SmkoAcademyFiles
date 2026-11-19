<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Asset extends Model
{
    public const TYPES = ['AUDIO', 'DOCUMENT', 'PHOTO', 'TEXT', 'VIDEO'];

    public const ICONS = [
        'AUDIO' => '🎵', 'DOCUMENT' => '📄', 'PHOTO' => '🖼️', 'TEXT' => '📝', 'VIDEO' => '🎬',
    ];

    /** Content types for inline previews. */
    public const MIME = [
        'pdf' => 'application/pdf',
        'mp3' => 'audio/mpeg', 'm4a' => 'audio/mp4', 'ogg' => 'audio/ogg', 'oga' => 'audio/ogg', 'wav' => 'audio/wav', 'opus' => 'audio/ogg',
        'mp4' => 'video/mp4', 'm4v' => 'video/mp4', 'webm' => 'video/webm', 'mov' => 'video/quicktime',
        'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'png' => 'image/png', 'gif' => 'image/gif', 'webp' => 'image/webp',
    ];

    /** New files are only announced on Telegram when someone ticks the box. */
    protected $attributes = ['notify' => false];

    protected $fillable = [
        'folder_id', 'name', 'type', 'file_ext', 'storage_key', 'size_bytes', 'size_label',
        'description', 'text', 'sort_order', 'notify', 'notified_at', 'content_md5',
    ];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'notify' => 'boolean', 'notified_at' => 'datetime'];
    }

    public function folder(): BelongsTo
    {
        return $this->belongsTo(Folder::class);
    }

    public function events(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AssetEvent::class);
    }

    /** Files added in the last N days count as "new". */
    public static function newSince(): \Carbon\CarbonInterface
    {
        return now()->subDays(config('smko.new_days'));
    }

    public function hasFile(): bool
    {
        return $this->type !== 'TEXT' && filled($this->file_ext);
    }

    /**
     * Object key in the bucket. Older files: {prefix}/{id}.{ext} (e.g. smko-files/downloads/290.mp3).
     * Newer old-site uploads keep their exact key in storage_key (smko-files/assets/{folder}/{random}.{ext}).
     */
    public function storageKey(): ?string
    {
        if (! $this->hasFile()) {
            return null;
        }

        return $this->storage_key ?: config('smko.prefix')."/{$this->id}.{$this->file_ext}";
    }

    /** File name offered to the user, e.g. "Track 1.5.mp3" */
    public function downloadName(): string
    {
        $base = trim(preg_replace('/[\\\\\/:*?"<>|]+/', ' ', $this->name)) ?: "file-{$this->id}";

        return $this->file_ext ? "{$base}.{$this->file_ext}" : $base;
    }

    /** Short-lived signed link straight to the bucket. */
    public function temporaryUrl(int $minutes = 45, bool $asAttachment = true): ?string
    {
        if (! $this->hasFile()) {
            return null;
        }

        // While ParsPack forces "Content-Disposition: attachment" on every response, an override
        // would arrive as a second, different header (Chrome rejects that), so none is sent.
        // Audio/video/images ignore the header; PDFs are previewed through LibraryController::preview.
        $options = [];
        if (! config('smko.bucket_forces_attachment')) {
            $filename = 'filename="'.Str::ascii($this->downloadName()).'"; filename*=UTF-8\'\''.rawurlencode($this->downloadName());
            $options = $asAttachment
                ? ['ResponseContentDisposition' => 'attachment; '.$filename]
                : array_filter(['ResponseContentDisposition' => 'inline; '.$filename, 'ResponseContentType' => self::MIME[$this->file_ext] ?? null]);
        }

        return Storage::disk(config('smko.disk'))->temporaryUrl($this->storageKey(), now()->addMinutes($minutes), $options);
    }

    /** Changes whenever the file is replaced — the Telegram bot uses it to refresh its cached copy. */
    public function versionHash(): string
    {
        // Based on the stored file only — editing the title or description must NOT
        // make the bot upload the file to Telegram again.
        // (A replaced file always gets a new storage key — see AssetFiles::attach.)
        return substr(sha1($this->storageKey().'|'.$this->size_bytes), 0, 16);
    }

    /**
     * How the file can be shown in the browser, decided by its real extension
     * (some old uploads are labelled DOCUMENT but are actually videos or images).
     * audio | video | image | pdf | text | none
     */
    public function previewKind(): string
    {
        if ($this->type === 'TEXT') {
            return 'text';
        }

        return match ($this->file_ext) {
            'mp3', 'm4a', 'ogg', 'oga', 'wav', 'aac', 'opus', 'flac' => 'audio',
            'mp4', 'm4v', 'webm', 'mov', 'mkv' => 'video',
            'jpg', 'jpeg', 'png', 'gif', 'webp' => 'image',
            'pdf' => 'pdf',
            default => 'none',
        };
    }

    public function sizeForHumans(): ?string
    {
        if ($this->size_bytes) {
            $units = ['B', 'KB', 'MB', 'GB'];
            $size = $this->size_bytes;
            $i = 0;
            while ($size >= 1024 && $i < count($units) - 1) {
                $size /= 1024;
                $i++;
            }

            return round($size, $i ? 2 : 0).$units[$i];
        }

        return $this->size_label;
    }
}
