<?php

namespace App\Support;

use App\Models\Asset;
use Aws\S3\Exception\S3Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a file from the bucket through this site, honouring HTTP Range requests
 * (needed for audio/video seeking and resumable downloads). Visitors then only need to
 * reach this site — not ParsPack directly — and files get proper names and types.
 */
class BucketStream
{
    /** Adds to today's traffic for the dashboard (one "hit" per play/download, not per seek). */
    public static function recordTraffic(Asset $asset, bool $attachment, int $bytes, bool $countHit): void
    {
        if ($bytes <= 0) {
            return;
        }
        try {
            $key = ['day' => now()->toDateString(), 'asset_id' => $asset->id, 'kind' => $attachment ? 'download' : 'preview'];
            $q = \Illuminate\Support\Facades\DB::table('traffic_daily')->where($key);
            if ($q->exists()) {
                $q->update([
                    'bytes' => \Illuminate\Support\Facades\DB::raw('bytes + '.$bytes),
                    'hits' => \Illuminate\Support\Facades\DB::raw('hits + '.($countHit ? 1 : 0)),
                ]);
            } else {
                \Illuminate\Support\Facades\DB::table('traffic_daily')->insert($key + ['bytes' => $bytes, 'hits' => $countHit ? 1 : 0]);
            }
        } catch (\Throwable) {
            // statistics must never break a download
        }
    }

    public static function response(Request $request, Asset $asset, bool $attachment): StreamedResponse
    {
        $diskName = config('smko.disk');
        $disk = Storage::disk($diskName);

        // Non-S3 disks (tests, local): stream the whole file, no ranges.
        if (! method_exists($disk, 'getClient')) {
            $stream = $disk->readStream($asset->storageKey());
            abort_unless($stream, 404);
            static::recordTraffic($asset, $attachment, (int) ($disk->size($asset->storageKey()) ?: 0), true);
            $name = $asset->downloadName();

            return new StreamedResponse(function () use ($stream) {
                fpassthru($stream);
                fclose($stream);
            }, 200, [
                'Content-Type' => Asset::MIME[$asset->file_ext] ?? 'application/octet-stream',
                'Content-Disposition' => ($attachment ? 'attachment' : 'inline').'; filename="'.Str::ascii($name).'"',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        }

        $client = $disk->getClient();
        $args = ['Bucket' => config("filesystems.disks.{$diskName}.bucket"), 'Key' => $asset->storageKey()];

        $range = $request->header('Range');
        if ($range && preg_match('/^bytes=(\d*)-(\d*)$/', trim($range))) {
            $args['Range'] = trim($range);
        }

        try {
            $result = $client->getObject($args + ['@http' => ['stream' => true]]);
        } catch (S3Exception $e) {
            if ($e->getStatusCode() === 416) {
                return new StreamedResponse(fn () => null, 416, ['Content-Range' => 'bytes */'.($asset->size_bytes ?? '*')]);
            }
            abort($e->getStatusCode() === 404 ? 404 : 502);
        }

        static::recordTraffic($asset, $attachment, (int) ($result['ContentLength'] ?? 0),
            ! isset($args['Range']) || str_starts_with($args['Range'], 'bytes=0-'));

        $name = $asset->downloadName();
        $headers = array_filter([
            'Content-Type' => Asset::MIME[$asset->file_ext] ?? ($attachment ? 'application/octet-stream' : ($result['ContentType'] ?? 'application/octet-stream')),
            'Content-Length' => $result['ContentLength'] ?? null,
            'Content-Range' => $result['ContentRange'] ?? null,
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => ($attachment ? 'attachment' : 'inline').'; filename="'.Str::ascii($name).'"; filename*=UTF-8\'\''.rawurlencode($name),
            'Cache-Control' => $attachment ? 'private, no-transform' : 'private, max-age=3600, no-transform',
            'X-Accel-Buffering' => 'no',
            'X-Content-Type-Options' => 'nosniff',
        ], fn ($v) => $v !== null && $v !== '');

        $body = $result['Body'];

        return new StreamedResponse(function () use ($body) {
            @set_time_limit(0);
            while (! $body->eof()) {
                echo $body->read(256 * 1024);
                flush();
                if (connection_aborted()) {
                    break; // visitor closed the player / cancelled the download
                }
            }
            $body->close();
        }, isset($result['ContentRange']) ? 206 : 200, $headers);
    }
}
