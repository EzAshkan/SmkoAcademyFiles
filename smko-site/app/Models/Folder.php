<?php

namespace App\Models;

use App\Support\FolderTree;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Folder extends Model
{
    protected $fillable = ['parent_id', 'name', 'sort_order'];

    protected static function booted(): void
    {
        static::saved(fn () => FolderTree::flush());
        static::deleted(fn () => FolderTree::flush());
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Folder::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Folder::class, 'parent_id')->orderBy('sort_order')->orderBy('id');
    }

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class)->orderBy('sort_order')->orderBy('id');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot('expires_at', 'granted_at');
    }

    /** "Adults / E4E1 / Lesson 01" */
    public function getPathAttribute(): string
    {
        return FolderTree::path($this->id);
    }
}
