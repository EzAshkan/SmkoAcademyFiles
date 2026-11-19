<?php
// Read-only check of bucket credentials: lists buckets and a few objects.
// Usage: php scripts/bucket-probe.php            (uses .env)
require __DIR__.'/../vendor/autoload.php';

use Aws\S3\S3Client;

Dotenv\Dotenv::createImmutable(__DIR__.'/..')->safeLoad();
$key = $_ENV['BUCKET_ACCESS_KEY_ID'] ?? '';
$secret = $_ENV['BUCKET_SECRET_ACCESS_KEY'] ?? '';
echo 'Secret set: '.($secret !== '' ? 'yes ('.strlen($secret).' chars)' : 'NO').PHP_EOL;
$s3 = new S3Client([
    'version' => 'latest',
    'region' => 'us-east-1',
    'endpoint' => 'https://c575538.parspack.net',
    'use_path_style_endpoint' => true,
    'credentials' => ['key' => $key, 'secret' => $secret],
    'http' => ['timeout' => 20, 'connect_timeout' => 10],
]);

try {
    $buckets = array_column($s3->listBuckets()['Buckets'] ?? [], 'Name');
    echo 'Buckets: '.implode(', ', $buckets).PHP_EOL;
} catch (Throwable $e) {
    echo 'listBuckets failed: '.($e instanceof Aws\Exception\AwsException ? $e->getAwsErrorCode().' '.$e->getAwsErrorMessage() : $e->getMessage()).PHP_EOL;
    $buckets = ['c575538', 'smko-files'];
}

foreach ($buckets as $b) {
    try {
        $r = $s3->listObjectsV2(['Bucket' => $b, 'MaxKeys' => 5, 'Delimiter' => '/']);
        echo "[$b] prefixes: ".implode(', ', array_column($r['CommonPrefixes'] ?? [], 'Prefix'))
            .' | keys: '.implode(', ', array_column($r['Contents'] ?? [], 'Key')).PHP_EOL;
    } catch (Throwable $e) {
        echo "[$b] failed: ".($e instanceof Aws\Exception\AwsException ? $e->getAwsErrorCode() : $e->getMessage()).PHP_EOL;
    }
}
