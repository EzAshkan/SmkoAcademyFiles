<?php

namespace App\Support;

use App\Models\AdminLog;
use App\Models\Asset;
use App\Models\Folder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Copy / move files and folders.
 * Copies point at the SAME object in the bucket (no duplicate upload, no extra storage).
 */
class LibraryOps
{
    /** @param int[] $ids */
    public static function moveAssets(array $ids, int $folderId): int
    {
        $folder = Folder::findOrFail($folderId);
        $order = (int) $folder->assets()->max('sort_order');
        $n = 0;
        foreach (Asset::whereIn('id', $ids)->orderBy('sort_order')->get() as $a) {
            if ($a->folder_id === $folder->id) {
                continue;
            }
            $a->update(['folder_id' => $folder->id, 'sort_order' => ++$order]);
            $n++;
        }
        AdminLog::add('moved', 'file', $folder->id, "Moved {$n} file(s) to ".FolderTree::path($folder->id));

        return $n;
    }

    /** @param int[] $ids */
    public static function copyAssets(array $ids, int $folderId): int
    {
        $folder = Folder::findOrFail($folderId);
        $order = (int) $folder->assets()->max('sort_order');
        $n = 0;
        DB::transaction(function () use ($ids, $folder, &$order, &$n) {
            foreach (Asset::whereIn('id', $ids)->orderBy('sort_order')->get() as $a) {
                static::cloneAsset($a, $folder->id, ++$order);
                $n++;
            }
        });
        AdminLog::add('copied', 'file', $folder->id, "Copied {$n} file(s) to ".FolderTree::path($folder->id));

        return $n;
    }

    /** @param int[] $ids */
    public static function moveFolders(array $ids, ?int $parentId): int
    {
        $n = 0;
        $order = (int) Folder::where('parent_id', $parentId)->max('sort_order');
        foreach (Folder::whereIn('id', $ids)->get() as $f) {
            if ($parentId && in_array($f->id, FolderTree::lineage($parentId), true)) {
                throw new InvalidArgumentException("\"{$f->name}\" can't be moved inside itself.");
            }
            if ($f->parent_id === $parentId) {
                continue;
            }
            $f->update(['parent_id' => $parentId, 'sort_order' => ++$order]);
            FolderTree::flush();
            $n++;
        }
        AdminLog::add('moved', 'folder', $parentId, "Moved {$n} folder(s) to ".($parentId ? FolderTree::path($parentId) : 'top level'));

        return $n;
    }

    /**
     * Deep copy: the folders, every sub-folder and every file (files share the bucket objects).
     * Access grants are not copied.
     *
     * @param  int[]  $ids
     * @return array{folders:int, files:int}
     */
    public static function copyFolders(array $ids, ?int $parentId): array
    {
        $count = ['folders' => 0, 'files' => 0];
        DB::transaction(function () use ($ids, $parentId, &$count) {
            $order = (int) Folder::where('parent_id', $parentId)->max('sort_order');
            foreach (Folder::whereIn('id', $ids)->get() as $f) {
                if ($parentId && in_array($f->id, FolderTree::lineage($parentId), true)) {
                    throw new InvalidArgumentException("\"{$f->name}\" can't be copied inside itself.");
                }
                $name = $f->parent_id === $parentId ? $f->name.' (copy)' : $f->name;
                static::cloneTree($f, $parentId, $name, ++$order, $count);
            }
        });
        FolderTree::flush();
        AdminLog::add('copied', 'folder', $parentId, "Copied {$count['folders']} folder(s) with {$count['files']} file(s) to ".($parentId ? FolderTree::path($parentId) : 'top level'));

        return $count;
    }

    protected static function cloneTree(Folder $src, ?int $parentId, string $name, int $order, array &$count): void
    {
        $copy = Folder::create(['parent_id' => $parentId, 'name' => $name, 'sort_order' => $order]);
        $count['folders']++;
        foreach ($src->assets()->get() as $a) {
            static::cloneAsset($a, $copy->id, $a->sort_order);
            $count['files']++;
        }
        foreach ($src->children()->get() as $child) {
            static::cloneTree($child, $copy->id, $child->name, $child->sort_order, $count);
        }
    }

    protected static function cloneAsset(Asset $a, int $folderId, int $order): Asset
    {
        $copy = $a->replicate(['notified_at']);
        $copy->folder_id = $folderId;
        $copy->sort_order = $order;
        // Point at the same bucket object as the original.
        $copy->storage_key = $a->hasFile() ? $a->storageKey() : null;
        $copy->notify = false;
        $copy->notified_at = now();
        $copy->save();

        return $copy;
    }

    /** True when another file row points at this bucket object (so it must not be deleted). */
    public static function keyIsShared(string $key, int $exceptAssetId): bool
    {
        if (Asset::where('id', '!=', $exceptAssetId)->where('storage_key', $key)->exists()) {
            return true;
        }
        // Standard-layout key of another asset: {prefix}/{id}.{ext}
        if (preg_match('~^'.preg_quote(config('smko.prefix'), '~').'/(\d+)\.(\w+)$~', $key, $m) && (int) $m[1] !== $exceptAssetId) {
            return Asset::where('id', (int) $m[1])->whereNull('storage_key')->where('file_ext', $m[2])->exists();
        }

        return false;
    }
}
