<?php
// Read-only: checks that every storage_key in media-export.json exists in the bucket.
// Usage: php scripts/verify-media-export.php ../media-export/media-export.json
require __DIR__.'/../vendor/autoload.php';

use Aws\S3\S3Client;

Dotenv\Dotenv::createImmutable(__DIR__.'/..')->safeLoad();
$s3 = new S3Client([
    'version' => 'latest', 'region' => 'us-east-1',
    'endpoint' => $_ENV['BUCKET_ENDPOINT'], 'use_path_style_endpoint' => true,
    'credentials' => ['key' => $_ENV['BUCKET_ACCESS_KEY_ID'], 'secret' => $_ENV['BUCKET_SECRET_ACCESS_KEY']],
    'http' => ['timeout' => 120, 'connect_timeout' => 15], 'retries' => 5,
]);
$bucket = $_ENV['BUCKET_NAME'];
$data = json_decode(file_get_contents($argv[1]), true);

$byDir = [];
foreach ($data['assets'] as $i => $a) {
    $byDir[dirname($a['storage_key']).'/'][] = $i;
}
$found = $missing = 0;
$sizes = [];
$missingList = [];
foreach ($byDir as $dir => $idx) {
    $keys = [];
    $token = null;
    do {
        $args = ['Bucket' => $bucket, 'Prefix' => $dir, 'MaxKeys' => 250];
        if ($token) {
            $args['ContinuationToken'] = $token;
        }
        $r = null;
        for ($t = 1; $t <= 4 && ! $r; $t++) {
            try {
                $r = $s3->listObjectsV2($args);
            } catch (Throwable $e) {
                sleep(2 * $t);
            }
        }
        foreach ($r['Contents'] ?? [] as $o) {
            $keys[$o['Key']] = [(int) $o['Size'], trim($o['ETag'], '"')];
        }
        $token = $r['NextContinuationToken'] ?? null;
    } while ($token);

    foreach ($idx as $i) {
        $k = $data['assets'][$i]['storage_key'];
        if (isset($keys[$k])) {
            $found++;
            $data['assets'][$i]['size_bytes'] = $keys[$k][0];
            $data['assets'][$i]['content_md5'] = preg_match('/^[a-f0-9]{32}$/', $keys[$k][1]) ? $keys[$k][1] : null;
            $data['assets'][$i]['in_bucket'] = true;
        } else {
            $missing++;
            $data['assets'][$i]['in_bucket'] = false;
            $missingList[] = $data['assets'][$i];
        }
    }
    echo '.';
}
file_put_contents($argv[1], json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
echo "\nFound in bucket: {$found}\nNot found: {$missing}\n";
foreach (array_slice($missingList, 0, 5) as $m) {
    echo "  - {$m['name']} ({$m['type']}, folder {$m['folder_id']}) key={$m['storage_key']}\n";
}
