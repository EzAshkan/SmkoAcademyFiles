<?php
// Read-only: walks the bucket folder by folder and prints file counts, sizes and sample names.
// Usage: php scripts/bucket-survey.php [prefix] [maxDepth]
require __DIR__.'/../vendor/autoload.php';

use Aws\S3\S3Client;

Dotenv\Dotenv::createImmutable(__DIR__.'/..')->safeLoad();
$s3 = new S3Client([
    'version' => 'latest', 'region' => 'us-east-1',
    'endpoint' => $_ENV['BUCKET_ENDPOINT'], 'use_path_style_endpoint' => true,
    'credentials' => ['key' => $_ENV['BUCKET_ACCESS_KEY_ID'], 'secret' => $_ENV['BUCKET_SECRET_ACCESS_KEY']],
    'http' => ['timeout' => 120, 'connect_timeout' => 15],
    'retries' => 3,
]);
$bucket = $_ENV['BUCKET_NAME'];

function walk(S3Client $s3, string $bucket, string $prefix, int $depth, int $max): void
{
    $files = 0; $bytes = 0; $samples = []; $subs = []; $token = null;
    do {
        $args = ['Bucket' => $bucket, 'Prefix' => $prefix, 'Delimiter' => '/', 'MaxKeys' => 1000];
        if ($token) $args['ContinuationToken'] = $token;
        try {
            $r = $s3->listObjectsV2($args);
        } catch (Throwable $e) {
            echo str_repeat('  ', $depth)."{$prefix}  ERROR ".substr($e->getMessage(), 0, 100).PHP_EOL;
            return;
        }
        foreach ($r['Contents'] ?? [] as $o) {
            $files++; $bytes += $o['Size'];
            if (count($samples) < 4) $samples[] = substr($o['Key'], strlen($prefix));
        }
        foreach ($r['CommonPrefixes'] ?? [] as $p) $subs[] = $p['Prefix'];
        $token = $r['NextContinuationToken'] ?? null;
    } while ($token);

    printf("%s%-45s %6d files %8.2f GB  %d subfolders  e.g. %s\n",
        str_repeat('  ', $depth), $prefix === '' ? '(bucket root)' : $prefix, $files, $bytes / 1073741824, count($subs), implode(', ', $samples));

    if ($depth < $max) {
        foreach (array_slice($subs, 0, 30) as $sub) walk($s3, $bucket, $sub, $depth + 1, $max);
        if (count($subs) > 30) echo str_repeat('  ', $depth + 1).'… '.(count($subs) - 30)." more subfolders\n";
    }
}

walk($s3, $bucket, $argv[1] ?? '', 0, (int) ($argv[2] ?? 2));
