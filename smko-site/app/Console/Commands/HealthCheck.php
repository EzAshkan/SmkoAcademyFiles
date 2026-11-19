<?php

namespace App\Console\Commands;

use App\Models\Asset;
use App\Models\HealthCheck as Check;
use App\Models\User;
use App\Support\TelegramRelay;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Checks the parts the site depends on and messages admins on Telegram when
 * something breaks or recovers. Runs every 5 minutes (routes/console.php).
 */
class HealthCheck extends Command
{
    protected $signature = 'smko:health {--quiet-alerts : Do not send Telegram alerts}';

    protected $description = 'Check database, bucket, Telegram bot, queue and disk; alert admins on changes';

    public const LABELS = [
        'database' => 'Database',
        'bucket' => 'File storage (ParsPack)',
        'bot' => 'Telegram bot server',
        'queue' => 'Background jobs',
        'disk' => 'Server disk space',
        'scheduler' => 'Scheduled tasks',
    ];

    public function handle(): int
    {
        $results = [
            'database' => $this->check(fn () => DB::select('select 1') ? 'OK' : throw new \RuntimeException('no answer')),
            'bucket' => $this->check(function () {
                $a = Asset::whereNotNull('file_ext')->inRandomOrder()->first();
                if (! $a) {
                    return 'No files yet';
                }
                $t = microtime(true);
                $size = Storage::disk(config('smko.disk'))->size($a->storageKey());

                return 'Read a file in '.round((microtime(true) - $t) * 1000).' ms ('.number_format($size / 1048576, 1).' MB)';
            }),
            'bot' => $this->check(function () {
                $url = config('smko.telegram.relay_url');
                if (! $url) {
                    return 'Not configured (local testing)';
                }
                $r = Http::timeout(10)->get(rtrim($url, '/').'/up');
                if (! $r->successful()) {
                    throw new \RuntimeException('HTTP '.$r->status());
                }

                return 'Reachable';
            }),
            'queue' => $this->check(function () {
                $failed = DB::table('failed_jobs')->where('failed_at', '>=', now()->subDay())->count();
                $oldest = DB::table('jobs')->min('available_at');
                $waiting = $oldest ? now()->timestamp - (int) $oldest : 0;
                if ($waiting > 900) {
                    throw new \RuntimeException('Jobs waiting '.round($waiting / 60).' min — is the queue worker running?');
                }
                if ($failed > 20) {
                    throw new \RuntimeException("{$failed} failed jobs in the last 24 h");
                }

                return DB::table('jobs')->count().' waiting · '.$failed.' failed (24 h)';
            }),
            'disk' => $this->check(function () {
                $free = @disk_free_space(base_path());
                $total = @disk_total_space(base_path());
                if (! $free || ! $total) {
                    return 'Unknown';
                }
                $pct = round($free / $total * 100);
                if ($pct < 10) {
                    throw new \RuntimeException("Only {$pct}% free (".round($free / 1073741824, 1).' GB)');
                }

                return "{$pct}% free (".round($free / 1073741824, 1).' GB)';
            }),
            'scheduler' => $this->check(fn () => 'Running'), // this command itself runs from the scheduler
        ];

        $alerts = [];
        foreach ($results as $name => [$ok, $message]) {
            $prev = Check::find($name);
            $changed = ! $prev || $prev->ok !== $ok;
            Check::updateOrCreate(['name' => $name], [
                'ok' => $ok,
                'message' => mb_substr($message, 0, 500),
                'checked_at' => now(),
                'changed_at' => $changed ? now() : $prev->changed_at,
            ]);
            if ($prev && $changed) {
                $alerts[] = ($ok ? '✅ Back to normal: ' : '🔴 Problem: ').self::LABELS[$name].' — '.$message;
            }
            $this->line(($ok ? '✔ ' : '✘ ').str_pad(self::LABELS[$name], 26).$message);
        }

        if ($alerts && ! $this->option('quiet-alerts')) {
            $this->alertAdmins("SMKO Files monitoring\n\n".implode("\n", $alerts));
        }

        return self::SUCCESS;
    }

    /** @return array{0: bool, 1: string} */
    protected function check(callable $fn): array
    {
        try {
            return [true, (string) $fn()];
        } catch (\Throwable $e) {
            return [false, mb_substr($e->getMessage(), 0, 300)];
        }
    }

    protected function alertAdmins(string $text): void
    {
        $messages = User::where('is_admin', true)->get()
            ->filter(fn (User $u) => $u->telegramChatId())
            ->map(fn (User $u) => ['chat_id' => $u->telegramChatId(), 'text' => $text])
            ->values()->all();
        try {
            TelegramRelay::send($messages);
        } catch (\Throwable $e) {
            report($e); // the bot itself may be what's down
        }
    }
}
