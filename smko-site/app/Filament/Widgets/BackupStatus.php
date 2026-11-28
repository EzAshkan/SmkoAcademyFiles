<?php

namespace App\Filament\Widgets;

use App\Support\Backups;
use Filament\Notifications\Notification;
use Filament\Widgets\Widget;

class BackupStatus extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.backup-status';

    public bool $showAll = false;

    public function backupNow(): void
    {
        try {
            $b = Backups::run();
            Notification::make()->title('Backup created')->body($b['name'].' · '.number_format($b['size'] / 1048576, 2).' MB')->success()->send();
        } catch (\Throwable $e) {
            report($e);
            Notification::make()->title('Backup failed')->body(mb_substr($e->getMessage(), 0, 200))->danger()->send();
        }
    }

    protected function getViewData(): array
    {
        $list = Backups::list();
        $last = $list->first();

        return [
            'list' => $this->showAll ? $list : $list->take(5),
            'count' => $list->count(),
            'totalMb' => $list->sum('size') / 1048576,
            'last' => $last,
            'overdue' => ! $last || $last['at']->lt(now()->subDays((int) config('smko.backups.every_days') + 1)),
            'next' => Backups::nextRun(),
            'keep' => (int) config('smko.backups.keep'),
            'every' => (int) config('smko.backups.every_days'),
        ];
    }
}
