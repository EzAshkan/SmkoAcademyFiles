<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\FolderTree;
use App\Support\StoragePaths;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/**
 * Loads smko-export.json (made from the old admin panel) into this site.
 *
 * Folders, files and access are replaced completely, so it can be run again on
 * cutover evening with a fresh export. Students are added or updated by ID;
 * existing students keep the password they already chose here.
 */
class ImportExport extends Command
{
    protected $signature = 'smko:import
        {file : Path to smko-export.json}
        {--admin=* : User ID(s) to make admin (e.g. --admin=1)}
        {--force : Do not ask for confirmation}';

    protected $description = 'Import folders, files, students and access from the old site export';

    public function handle(): int
    {
        $path = $this->argument('file');
        if (! is_file($path)) {
            $this->error("File not found: {$path}");

            return self::FAILURE;
        }

        $data = json_decode(file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        foreach (['folders', 'assets', 'users', 'access'] as $key) {
            if (! isset($data[$key])) {
                $this->error("Export is missing '{$key}'.");

                return self::FAILURE;
            }
        }

        $this->info('Export from '.($data['exported_at'] ?? 'unknown date').': '
            .count($data['folders']).' folders, '.count($data['assets']).' files, '
            .count($data['users']).' students.');

        if (! $this->option('force') && ! $this->confirm('This replaces ALL folders, files and access on this site. Continue?')) {
            return self::FAILURE;
        }

        $admins = array_map('intval', $this->option('admin'));

        DB::transaction(function () use ($data, $admins) {
            $this->importUsers($data['users'], $admins);
            DB::table('telegram_claims')->delete();
            DB::table('folder_user')->delete();
            DB::table('assets')->delete();
            DB::table('folders')->delete();
            $this->importFolders($data['folders']);
            $this->importAssets($data['assets'], $data['folders']);
            $this->importAccess($data['access'], $data['folders'], $data['users']);
        });

        FolderTree::flush();

        // Exact bucket keys collected from the old site, if saved next to the export.
        $pathsFile = dirname($path).DIRECTORY_SEPARATOR.'storage-paths.json';
        if (is_file($pathsFile)) {
            $r = StoragePaths::apply(StoragePaths::load($pathsFile));
            $this->line("Bucket locations: {$r['applied']} applied from storage-paths.json.");
        }

        $this->newLine();
        $this->table(['What', 'Rows'], [
            ['Students', DB::table('users')->count()],
            ['Admins', DB::table('users')->where('is_admin', true)->count()],
            ['Folders', DB::table('folders')->count()],
            ['Files', DB::table('assets')->count()],
            ['Access grants', DB::table('folder_user')->count()],
        ]);
        $this->comment('Next: run "php artisan smko:sync-bucket" to fill in file extensions and sizes from the bucket.');

        return self::SUCCESS;
    }

    protected function importUsers(array $users, array $admins): void
    {
        $hash = Hash::make(User::DEFAULT_PASSWORD);
        $now = now();
        $existing = DB::table('users')->pluck('id')->flip();
        $new = 0;

        foreach ($users as $u) {
            $id = (int) $u['id'];
            $phone = trim((string) $u['telegram_id']);
            $row = [
                'name' => trim($u['name']) ?: "User {$id}",
                'phone' => $phone,
                'telegram_id' => $phone,
                'updated_at' => $now,
            ];
            if (in_array($id, $admins, true)) {
                $row['is_admin'] = true;
            }

            if (isset($existing[$id])) {
                DB::table('users')->where('id', $id)->update($row);
            } else {
                DB::table('users')->insert($row + [
                    'id' => $id,
                    'password' => $hash,
                    'must_change_password' => true,
                    'is_admin' => in_array($id, $admins, true),
                    'created_at' => $now,
                ]);
                $new++;
            }
        }

        $this->line("Students: {$new} new, ".(count($users) - $new).' updated.');
    }

    protected function importFolders(array $folders): void
    {
        // Insert parents before children.
        $depth = function (array $f) use ($folders, &$depth): int {
            return $f['parent_id'] && isset($folders[$f['parent_id']]) ? 1 + $depth($folders[$f['parent_id']]) : 0;
        };
        $rows = array_values($folders);
        usort($rows, fn ($a, $b) => [$depth($a), $a['id']] <=> [$depth($b), $b['id']]);

        $now = now();
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('folders')->insert(array_map(fn ($f) => [
                'id' => (int) $f['id'],
                'parent_id' => $f['parent_id'] ? (int) $f['parent_id'] : null,
                'name' => $f['name'],
                'sort_order' => (int) $f['order'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $chunk));
        }
    }

    protected function importAssets(array $assets, array $folders): void
    {
        $now = now();
        $rows = [];
        $skipped = 0;

        foreach ($assets as $a) {
            if (! $a['folder_id'] || ! isset($folders[$a['folder_id']])) {
                $skipped++;

                continue;
            }
            $d = $a['detail'] ?? [];
            $created = null;
            if (! empty($d['created_at'])) {
                try {
                    $created = Carbon::parse($d['created_at']);
                } catch (\Throwable) {
                }
            }

            $rows[] = [
                'id' => (int) $a['id'],
                'folder_id' => (int) $a['folder_id'],
                'name' => ($d['name'] ?? null) ?: $a['name'],
                'type' => $a['type'] ?: ($d['type'] ?? 'DOCUMENT'),
                'file_ext' => null,
                'size_bytes' => null,
                'size_label' => ($d['size'] ?? null) ?: null,
                'description' => ($d['description'] ?? null) ?: null,
                'text' => ($d['text'] ?? null) ?: null,
                'sort_order' => (int) $a['order'],
                'notified_at' => $now,
                'created_at' => $created ?? $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('assets')->insert($chunk);
        }

        if ($skipped) {
            $this->warn("Skipped {$skipped} files with no folder.");
        }
    }

    protected function importAccess(array $access, array $folders, array $users): void
    {
        $rows = [];
        foreach ($access as $folderId => $userIds) {
            if (! isset($folders[$folderId])) {
                continue;
            }
            foreach (array_unique($userIds) as $uid) {
                if (isset($users[$uid])) {
                    $rows[] = ['folder_id' => (int) $folderId, 'user_id' => (int) $uid];
                }
            }
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('folder_user')->insert($chunk);
        }
    }
}
