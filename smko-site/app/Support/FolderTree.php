<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * In-memory view of the folder tree (~1,000 rows) used for paths and access checks.
 *
 * Access rule (same as the old site): a grant on a folder covers that folder and
 * everything below it. A student may be granted a sub-folder alone.
 */
class FolderTree
{
    /** @var array<int, array{id:int, parent_id:?int, name:string, sort_order:int}>|null */
    protected static ?array $nodes = null;

    /** @var array<int, array<int, bool>> userId => [folderId => true] */
    protected static array $grants = [];

    public static function flush(): void
    {
        static::$nodes = null;
        static::$grants = [];
    }

    public static function nodes(): array
    {
        if (static::$nodes === null) {
            static::$nodes = DB::table('folders')
                ->select('id', 'parent_id', 'name', 'sort_order')
                ->get()
                ->mapWithKeys(fn ($f) => [$f->id => (array) $f])
                ->all();
        }

        return static::$nodes;
    }

    /** @return int[] folder ids from the top-level folder down to $id (inclusive) */
    public static function lineage(int $id): array
    {
        $nodes = static::nodes();
        $chain = [];
        $guard = 0;
        while ($id && isset($nodes[$id]) && $guard++ < 100) {
            array_unshift($chain, $id);
            $id = (int) $nodes[$id]['parent_id'];
        }

        return $chain;
    }

    public static function path(int $id, string $sep = ' / '): string
    {
        $nodes = static::nodes();

        return implode($sep, array_map(fn ($i) => $nodes[$i]['name'], static::lineage($id)));
    }

    /** @return array<int, string> folder id => full path, sorted the way the tree is shown */
    public static function options(?int $exceptSubtreeOf = null): array
    {
        $ids = array_keys(static::nodes());
        if ($exceptSubtreeOf) {
            $ids = array_filter($ids, fn ($id) => ! in_array($exceptSubtreeOf, static::lineage($id), true));
        }
        usort($ids, fn ($a, $b) => static::sortKey($a) <=> static::sortKey($b));

        $out = [];
        foreach ($ids as $id) {
            $out[$id] = static::path($id);
        }

        return $out;
    }

    protected static function grantsFor(User $user): array
    {
        return static::$grants[$user->id] ??= DB::table('folder_user')
            ->where('user_id', $user->id)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->pluck('folder_id')
            ->mapWithKeys(fn ($id) => [$id => true])
            ->all();
    }

    public static function canView(User $user, int $folderId): bool
    {
        if ($user->is_admin) {
            return true;
        }

        $grants = static::grantsFor($user);
        foreach (static::lineage($folderId) as $id) {
            if (isset($grants[$id])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Folders shown on a student's home page: the highest folders they can open
     * (a granted folder whose parent they can't already open). Admins see the real top level.
     *
     * @return int[]
     */
    public static function entryPoints(User $user): array
    {
        $nodes = static::nodes();

        if ($user->is_admin) {
            $ids = array_keys(array_filter($nodes, fn ($n) => $n['parent_id'] === null));
        } else {
            $ids = array_values(array_filter(
                array_keys(static::grantsFor($user)),
                fn ($id) => isset($nodes[$id]) && ! ($nodes[$id]['parent_id'] && static::canView($user, (int) $nodes[$id]['parent_id']))
            ));
        }

        usort($ids, fn ($a, $b) => [static::sortKey($a)] <=> [static::sortKey($b)]);

        return $ids;
    }

    /** Sort by position in the tree so entry points appear in the admin's order. */
    protected static function sortKey(int $id): array
    {
        $nodes = static::nodes();

        return array_map(fn ($i) => [$nodes[$i]['sort_order'], $i], static::lineage($id));
    }
}
