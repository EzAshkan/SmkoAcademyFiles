<?php

namespace App\Console\Commands;

use App\Support\Backups;
use Illuminate\Console\Command;

class Backup extends Command
{
    protected $signature = 'smko:backup';

    protected $description = 'Back up the database to a compressed .zip (keeps the newest SMKO_BACKUP_KEEP)';

    public function handle(): int
    {
        try {
            $b = Backups::run();
        } catch (\Throwable $e) {
            $this->error($e->getMessage());
            report($e);

            return self::FAILURE;
        }
        $this->info(sprintf('Backup %s (%s). %d backups kept in %s.',
            $b['name'], number_format($b['size'] / 1048576, 2).' MB', Backups::list()->count(), Backups::dir()));

        return self::SUCCESS;
    }
}
