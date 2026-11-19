<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Confirms every file with an exact old-site key (storage_key) really exists in the bucket,
 * by listing each key's folder once (much faster than one request per file), and records sizes.
 */
class VerifyKeys extends Command
{
    protected $signature = 'smko:verify-keys {--report= : CSV of keys not found}';

    protected $description = 'Check exact bucket keys exist and record their sizes';

    public function handle(): int
    {
        $cfg = config('filesystems.disks.'.config('smko.disk'));
        $s3 = new S3Client([
            'version' => 'latest', 'region' => $cfg['region'], 'endpoint' => $cfg['endpoint'],
            'use_path_style_endpoint' => $cfg['use_path_style_endpoint'],
            'credentials' => ['key' => $cfg['key'], 'secret' => $cfg['secret']],
            'http' => ['timeout' => 120, 'connect_timeout' => 15], 'retries' => 5,
        ]);

        $rows = DB::table('assets')->whereNotNull('storage_key')->get(['id', 'storage_key']);
        $byDir = $rows->groupBy(fn ($r) => dirname($r->storage_key).'/');
        $this->info($rows->count().' keys in '.$byDir->count().' folders.');
        $bar = $this->output->createProgressBar($byDir->count());
        $missing = [];
        $found = 0;

        foreach ($byDir as $dir => $items) {
            $sizes = [];
            $token = null;
            try {
                do {
                    $args = ['Bucket' => $cfg['bucket'], 'Prefix' => $dir, 'MaxKeys' => 1000];
                    if ($token) {
                        $args['ContinuationToken'] = $token;
                    }
                    $r = $s3->listObjectsV2($args);
                    foreach ($r['Contents'] ?? [] as $o) {
                        $sizes[$o['Key']] = (int) $o['Size'];
                    }
                    $token = $r['NextContinuationToken'] ?? null;
                } while ($token);
            } catch (\Throwable $e) {
                $this->newLine();
                $this->warn("Could not list {$dir}: ".substr($e->getMessage(), 0, 120));
                $bar->advance();

                continue; // leave these untouched; run again later
            }

            foreach ($items as $item) {
                if (isset($sizes[$item->storage_key])) {
                    DB::table('assets')->where('id', $item->id)->update(['size_bytes' => $sizes[$item->storage_key]]);
                    $found++;
                } else {
                    $missing[] = $item;
                }
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);
        $this->info("Found in bucket: {$found}");
        $this->line('Not found: '.count($missing));

        if ($missing && ($report = $this->option('report'))) {
            $fh = fopen($report, 'w');
            fputcsv($fh, ['file_id', 'storage_key']);
            foreach ($missing as $m) {
                fputcsv($fh, [$m->id, $m->storage_key]);
            }
            fclose($fh);
            $this->line("Written to {$report}");
        }

        return self::SUCCESS;
    }
}
