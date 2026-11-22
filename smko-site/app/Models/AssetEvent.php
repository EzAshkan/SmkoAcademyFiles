<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One view / download / Telegram request of a file by a student (for statistics and "New" badges). */
class AssetEvent extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['user_id', 'asset_id', 'type', 'created_at'];

    protected function casts(): array
    {
        return ['created_at' => 'datetime'];
    }

    public static function record(?User $user, Asset $asset, string $type): void
    {
        // Visitors without an account (public mode) are counted anonymously.
        if ($user || \App\Support\Mode::isPublic()) {
            static::create(['user_id' => $user?->id, 'asset_id' => $asset->id, 'type' => $type]);
        }
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }
}
