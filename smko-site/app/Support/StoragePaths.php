<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Applies the exact bucket keys collected from the old site (storage-paths.json:
 * {"paths": {"<asset id>": "smko-files/assets/822/rX5Z….mp3", …}}).
 */
class StoragePaths
{
    /** @return array{applied:int, unknown:int} */
    public static function apply(array $paths): array
    {
        $prefix = config('smko.prefix');
        $applied = 0;
        $unknown = 0;
        $existing = DB::table('assets')->pluck('id')->flip();

        foreach ($paths as $id => $key) {
            $id = (int) $id;
            $key = ltrim((string) $key, '/');
            if (! isset($existing[$id]) || $key === '') {
                $unknown++;

                continue;
            }
            $ext = strtolower(pathinfo($key, PATHINFO_EXTENSION));
            $standard = "{$prefix}/{$id}.{$ext}";

            DB::table('assets')->where('id', $id)->update([
                'file_ext' => $ext ?: 'bin',
                // Only store the key when it differs from the standard {prefix}/{id}.{ext} layout.
                'storage_key' => $key === $standard ? null : $key,
            ]);
            $applied++;
        }

        return ['applied' => $applied, 'unknown' => $unknown];
    }

    public static function load(string $file): array
    {
        $data = json_decode(file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

        return $data['paths'] ?? $data;
    }
}
