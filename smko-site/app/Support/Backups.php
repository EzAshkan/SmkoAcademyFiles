<?php

namespace App\Support;

use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use RuntimeException;
use Symfony\Component\Process\Process;
use ZipArchive;

/**
 * Database backups: the site's database is dumped and compressed into a .zip.
 * The newest config('smko.backups.keep') are kept; older ones are deleted.
 */
class Backups
{
    public static function dir(): string
    {
        $dir = config('smko.backups.path') ?: storage_path('app/backups');
        if (! is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return rtrim($dir, '/\\');
    }

    /** @return Collection<int, array{name:string, path:string, size:int, at:Carbon}> newest first */
    public static function list(): Collection
    {
        return collect(glob(static::dir().'/'.static::prefix().'*.zip') ?: [])
            ->map(fn ($p) => ['name' => basename($p), 'path' => $p, 'size' => filesize($p), 'at' => Carbon::createFromTimestamp(filemtime($p))])
            ->sortByDesc('at')->values();
    }

    public static function latest(): ?array
    {
        return static::list()->first();
    }

    /** Creates a new backup and prunes old ones. Returns the new backup's info. */
    public static function run(): array
    {
        $db = config('database.connections.'.config('database.default'));
        $stamp = now()->format('Y-m-d_His');
        $sql = static::dir()."/{$stamp}.sql";
        $zip = static::dir().'/'.static::prefix()."{$stamp}.zip";

        if (($db['driver'] ?? '') === 'sqlite') {
            copy($db['database'], $sql); // local development
        } else {
            $process = new Process([
                'mariadb-dump', '--single-transaction', '--quick', '--routines', '--no-tablespaces', '--skip-lock-tables',
                '-h', $db['host'], '-P', (string) $db['port'], '-u', $db['username'], $db['database'],
                '--result-file='.$sql,
            ], null, ['MYSQL_PWD' => (string) $db['password']]);
            $process->setTimeout(1800)->run();
            if (! $process->isSuccessful()) {
                @unlink($sql);
                throw new RuntimeException('Database dump failed: '.trim($process->getErrorOutput()));
            }
        }

        $z = new ZipArchive;
        if ($z->open($zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            @unlink($sql);
            throw new RuntimeException('Could not create '.$zip);
        }
        $z->addFile($sql, basename($sql));
        $z->setCompressionName(basename($sql), ZipArchive::CM_DEFLATE, 9);
        $z->close();
        @unlink($sql);

        static::prune();

        return static::latest();
    }

    public static function prune(): int
    {
        $old = static::list()->slice((int) config('smko.backups.keep'));
        foreach ($old as $b) {
            @unlink($b['path']);
        }

        return $old->count();
    }

    public static function nextRun(): Carbon
    {
        // Every N days at 03:00, counted from the day of the month like cron "*/N".
        $every = (int) config('smko.backups.every_days');
        $t = now()->copy()->setTime(3, 0);
        while ($t->lte(now()) || ($t->day - 1) % $every !== 0) {
            $t->addDay();
        }

        return $t;
    }

    /** One prefix per site, so files and media backups never mix in a shared folder. */
    public static function prefix(): string
    {
        return \Illuminate\Support\Str::slug(config('app.name', 'smko')).'-';
    }
}
