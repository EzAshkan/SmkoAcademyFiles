<?php

namespace App\Support;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * "New" = added in the last config('smko.new_days') days and not yet opened by this student.
 */
class NewFiles
{
    /** @var array<int, Collection> */
    protected static array $cache = [];

    /** Recent files this user may open and hasn't opened yet (newest first). */
    public static function unseenFor(?User $user): Collection
    {
        return static::$cache[$user?->id ?? 0] ??= (function () use ($user) {
            // Visitors without an account (public mode): every recent file counts as new.
            $seen = $user
                ? DB::table('asset_events')->where('user_id', $user->id)
                    ->where('created_at', '>=', Asset::newSince())->distinct()->pluck('asset_id')->flip()
                : collect();

            return Asset::query()
                ->where('created_at', '>=', Asset::newSince())
                ->orderByDesc('created_at')->orderByDesc('id')
                ->get()
                ->filter(fn (Asset $a) => ! isset($seen[$a->id]) && FolderTree::canView($user, $a->folder_id))
                ->values();
        })();
    }

    /** @return array<int, true> asset ids */
    public static function idsFor(?User $user): array
    {
        return static::unseenFor($user)->pluck('id')->flip()->map(fn () => true)->all();
    }

    /** Number of new files inside each folder (counting everything below it). @return array<int, int> */
    public static function countsByFolder(?User $user): array
    {
        $counts = [];
        foreach (static::unseenFor($user) as $a) {
            foreach (FolderTree::lineage($a->folder_id) as $fid) {
                $counts[$fid] = ($counts[$fid] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /** Recently added files for the home page (seen or not), newest first. */
    public static function recentFor(?User $user, int $limit = 12): Collection
    {
        return Asset::query()
            ->where('created_at', '>=', Asset::newSince())
            ->orderByDesc('created_at')->orderByDesc('id')
            ->limit(400)
            ->get()
            ->filter(fn (Asset $a) => FolderTree::canView($user, $a->folder_id))
            ->take($limit)
            ->values();
    }

    public static function flush(): void
    {
        static::$cache = [];
    }
}
