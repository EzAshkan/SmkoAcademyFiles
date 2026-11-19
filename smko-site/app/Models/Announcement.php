<?php

namespace App\Models;

use App\Support\FolderTree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class Announcement extends Model
{
    public const STYLES = ['info' => 'Info (blue)', 'success' => 'Good news (green)', 'warning' => 'Warning (amber)', 'danger' => 'Urgent (red)'];

    protected $fillable = [
        'title', 'body', 'style', 'folder_ids', 'show_banner', 'starts_at', 'ends_at',
        'send_telegram', 'telegram_sent_at', 'telegram_sent_count', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'folder_ids' => 'array',
            'show_banner' => 'boolean',
            'send_telegram' => 'boolean',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'telegram_sent_at' => 'datetime',
        ];
    }

    public function scopeActiveBanners(Builder $q): Builder
    {
        return $q->where('show_banner', true)
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->latest();
    }

    public function isForEveryone(): bool
    {
        return empty($this->folder_ids);
    }

    public function isFor(User $user): bool
    {
        if ($this->isForEveryone() || $user->is_admin) {
            return true;
        }
        foreach ($this->folder_ids as $id) {
            if (FolderTree::canView($user, (int) $id)) {
                return true;
            }
        }

        return false;
    }

    /** Students who should receive this announcement. */
    public function audience(): Collection
    {
        $users = User::query()->where('is_admin', false)->get();

        return $this->isForEveryone() ? $users : $users->filter(fn (User $u) => $this->isFor($u))->values();
    }
}
