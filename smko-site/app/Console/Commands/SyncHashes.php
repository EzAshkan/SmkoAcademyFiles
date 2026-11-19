<?php

namespace App\Console\Commands;

use Aws\S3\S3Client;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Reads each file's fingerprint (ETag = MD5 for normal uploads) from the bucket listing,
 * so an upload that's already in the bucket can be recognised and reused.
 */
class SyncHashes extends Command
{
    protected $signature = 'smko:sync-hashes {--only-missing : Only folders that still have files without a fingerprint}';

    protected $description = 'Record MD5 fingerprints of the files in the bucket';

    public function handle(): int
    {
        $cfg = config('filesystems.disks.'.config('smko.disk'));
        $s3 = new S3Client([
            'version' => 'latest', 'region' => $cfg['region'], 'endpoint' => $cfg['endpoint'],
            'use_path_style_endpoint' => $cfg['use_path_style_endpoint'],
            'credentials' => ['key' => $cfg['key'], 'secret' => $cfg['secret']],
            'http' => ['timeout' => 120, 'connect_timeout' => 15], 'retries' => 5,
        ]);

        // Every asset's key, grouped by bucket folder.
        $rows = DB::table('assets')->whereNotNull('file_ext')
            ->when($this->option('only-missing'), fn ($q) => $q->whereNull('content_md5'))
            ->get(['id', 'storage_key', 'file_ext']);
        $keyToIds = [];
        foreach ($rows as $r) {
            $key = $r->storage_key ?: config('smko.prefix')."/{$r->id}.{$r->file_ext}";
            $keyToIds[$key][] = $r->id;
        }
        $dirs = collect(array_keys($keyToIds))->map(fn ($k) => dirname($k).'/')->unique()->values();
        $this->info(count($keyToIds).' objects in '.$dirs->count().' bucket folders.');

        $bar = $this->output->createProgressBar($dirs->count());
        $set = 0;
        $multipart = 0;
        foreach ($dirs as $dir) {
            $token = null;
            do {
                // Small pages: ParsPack sometimes fails on large listings.
                $args = ['Bucket' => $cfg['bucket'], 'Prefix' => $dir, 'MaxKeys' => 250];
                if ($token) {
                    $args['ContinuationToken'] = $token;
                }
                $res = null;
                for ($try = 1; $try <= 4 && ! $res; $try++) {
                    try {
                        $res = $s3->listObjectsV2($args);
                    } catch (\Throwable $e) {
                        if ($try === 4) {
                            $this->newLine();
                            $this->warn("Skipped {$dir}: ".substr($e->getMessage(), 0, 100));
                        } else {
                            sleep(3 * $try);
                        }
                    }
                }
                if (! $res) {
                    break;
                }
                foreach ($res['Contents'] ?? [] as $o) {
                    if (! isset($keyToIds[$o['Key']])) {
                        continue;
                    }
                    $etag = trim($o['ETag'], '"');
                    if (! preg_match('/^[a-f0-9]{32}$/', $etag)) {
                        $multipart++; // uploaded in parts: the ETag isn't the MD5

                        continue;
                    }
                    DB::table('assets')->whereIn('id', $keyToIds[$o['Key']])->update(['content_md5' => $etag]);
                    $set++;
                }
                $token = $res['NextContinuationToken'] ?? null;
            } while ($token);
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);
        $this->info("Fingerprints recorded: {$set}. Large multi-part uploads without one: {$multipart}.");

        return self::SUCCESS;
    }
}
