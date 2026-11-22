<?php

namespace App\Console\Commands;

use App\Support\AssetFiles;
use App\Support\FolderTree;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Loads media-export.json (read from the public media.smkoacademy.ir site) into this site —
 * meant for the public deployment (SMKO_PUBLIC_MODE=true).
 *
 * Folders keep their original numbers. Files point at their existing objects in the bucket
 * (smko-media/assets/...), so nothing is copied or uploaded. Folders and files on this site
 * are replaced completely, so it can be run again with a fresh export.
 */
class ImportMedia extends Command
{
    protected $signature = 'smko:import-media
        {file : Path to media-export.json}
        {--include-missing : Also import files whose object was not found in the bucket}
        {--force : Do not ask for confirmation}';

    protected $description = 'Import folders and files from the media site export';

    public function handle(): int
    {
        $data = json_decode(file_get_contents($this->argument('file')), true, flags: JSON_THROW_ON_ERROR);
        $folders = $data['folders'] ?? [];
        $assets = $data['assets'] ?? [];
        $this->info('Export from '.($data['exported_at'] ?? '?').': '.count($folders).' folders, '.count($assets).' files.');

        if (! $this->option('force') && ! $this->confirm('This replaces ALL folders and files on this site. Continue?')) {
            return self::FAILURE;
        }

        $skipped = 0;
        DB::transaction(function () use ($folders, $assets, &$skipped) {
            DB::table('asset_events')->delete();
            DB::table('telegram_claims')->delete();
            DB::table('folder_user')->delete();
            DB::table('assets')->delete();
            DB::table('folders')->delete();

            // Parents before children.
            $depth = function (array $f) use ($folders, &$depth): int {
                return $f['parent_id'] && isset($folders[$f['parent_id']]) ? 1 + $depth($folders[$f['parent_id']]) : 0;
            };
            $rows = array_values($folders);
            usort($rows, fn ($a, $b) => [$depth($a), $a['id']] <=> [$depth($b), $b['id']]);
            $now = now();
            foreach ($rows as $f) {
                DB::table('folders')->insert([
                    'id' => (int) $f['id'],
                    'parent_id' => $f['parent_id'] ? (int) $f['parent_id'] : null,
                    'name' => $f['name'],
                    'sort_order' => (int) $f['order'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }

            foreach ($assets as $a) {
                $key = $a['storage_key'] ?? '';
                $broken = ! preg_match('~^smko-media/.+\.[A-Za-z0-9]+$~', $key) || (($a['in_bucket'] ?? true) === false);
                if (! $a['folder_id'] || ! isset($folders[$a['folder_id']]) || ($broken && ! $this->option('include-missing'))) {
                    $skipped++;

                    continue;
                }
                try {
                    $created = Carbon::parse($a['modified'] ?: $a['created_at']);
                } catch (\Throwable) {
                    $created = $now;
                }
                DB::table('assets')->insert([
                    'folder_id' => (int) $a['folder_id'],
                    'name' => $a['name'] ?: 'File',
                    'type' => in_array($a['type'], ['AUDIO', 'DOCUMENT', 'PHOTO', 'TEXT', 'VIDEO'], true) ? $a['type'] : 'DOCUMENT',
                    'file_ext' => $broken ? null : AssetFiles::cleanExtension($key),
                    'storage_key' => $broken ? null : $key,
                    'size_bytes' => $a['size_bytes'] ?? null,
                    'size_label' => $a['size'] ?: null,
                    'content_md5' => $a['content_md5'] ?? null,
                    'description' => ($a['description'] ?? '') ?: null,
                    'text' => ($a['text'] ?? '') ?: null,
                    'sort_order' => (int) $a['order'],
                    'notify' => false,
                    'notified_at' => $now,
                    'created_at' => $created,
                    'updated_at' => $now,
                ]);
            }
        });

        FolderTree::flush();
        $this->table(['What', 'Rows'], [
            ['Folders', DB::table('folders')->count()],
            ['Files', DB::table('assets')->count()],
            ['Skipped (no file in the bucket)', $skipped],
        ]);

        return self::SUCCESS;
    }
}
