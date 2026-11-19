<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Lists the bucket once and matches every object "{prefix}/{id}.{ext}" to its
 * file row, filling in the extension and real size. Reports files that are
 * missing from the bucket and objects that have no matching file.
 */
class SyncBucket extends Command
{
    protected $signature = 'smko:sync-bucket {--report= : Write a CSV of problems to this path}';

    protected $description = 'Match files in the database to objects in the bucket';

    public function handle(): int
    {
        $disk = Storage::disk(config('smko.disk'));
        $prefix = config('smko.prefix');

        $this->info("Listing bucket under '{$prefix}/' …");
        $objects = [];
        $unmatched = [];
        foreach ($disk->listContents($prefix, false) as $item) {
            if (! $item->isFile()) {
                continue;
            }
            $name = basename($item->path());
            if (preg_match('/^(\d+)\.([A-Za-z0-9]+)$/', $name, $m)) {
                $objects[(int) $m[1]] = ['ext' => strtolower($m[2]), 'size' => $item->fileSize()];
            } else {
                $unmatched[] = $item->path();
            }
        }
        $this->line(count($objects).' objects found.');

        $missing = [];
        $updated = 0;
        DB::table('assets')->select('id', 'type', 'name', 'folder_id', 'storage_key')->orderBy('id')->chunk(500, function ($rows) use ($objects, &$missing, &$updated) {
            foreach ($rows as $row) {
                if ($row->storage_key) {
                    continue; // exact key from the old site — checked by smko:import-paths --verify
                }
                if (isset($objects[$row->id])) {
                    DB::table('assets')->where('id', $row->id)->update([
                        'file_ext' => $objects[$row->id]['ext'],
                        'size_bytes' => $objects[$row->id]['size'],
                    ]);
                    $updated++;
                } elseif ($row->type !== 'TEXT') {
                    $missing[] = $row;
                }
            }
        });

        $known = DB::table('assets')->pluck('id')->flip();
        $orphans = array_keys(array_diff_key($objects, $known->all()));

        $this->table(['Result', 'Count'], [
            ['Files matched to the bucket', $updated],
            ['Files missing from the bucket (not TEXT)', count($missing)],
            ['Bucket objects with no file in the database', count($orphans)],
            ['Bucket objects with unexpected names', count($unmatched)],
        ]);

        if ($report = $this->option('report')) {
            $fh = fopen($report, 'w');
            fwrite($fh, "\xEF\xBB\xBF");
            fputcsv($fh, ['problem', 'id_or_key', 'name', 'folder_id']);
            foreach ($missing as $r) {
                fputcsv($fh, ['missing_in_bucket', $r->id, $r->name, $r->folder_id]);
            }
            foreach ($orphans as $id) {
                fputcsv($fh, ['no_database_row', $id, '', '']);
            }
            foreach ($unmatched as $key) {
                fputcsv($fh, ['unexpected_name', $key, '', '']);
            }
            fclose($fh);
            $this->info("Report written to {$report}");
        }

        return self::SUCCESS;
    }
}
