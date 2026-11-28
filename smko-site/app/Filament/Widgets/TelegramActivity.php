<?php

namespace App\Filament\Widgets;

use App\Support\Mode;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/** New-file alerts, announcements and admin alerts handed to the Telegram bot. */
class TelegramActivity extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.telegram-activity';

    public const KINDS = [
        'new_files' => ['New-file alerts', 'bg-emerald-50 text-emerald-700 dark:bg-emerald-500/10 dark:text-emerald-300'],
        'announcement' => ['Announcements', 'bg-sky-50 text-sky-700 dark:bg-sky-500/10 dark:text-sky-300'],
        'monitoring' => ['Monitoring alerts', 'bg-amber-50 text-amber-700 dark:bg-amber-500/10 dark:text-amber-300'],
        'error' => ['Error alerts', 'bg-red-50 text-red-700 dark:bg-red-500/10 dark:text-red-300'],
    ];

    public static function canView(): bool
    {
        return Mode::hasStudents();
    }

    protected function getViewData(): array
    {
        $since = now()->subDays(30);
        $totals = DB::table('telegram_sends')->where('created_at', '>=', $since)
            ->selectRaw('kind, COUNT(*) batches, SUM(recipients) recipients, SUM(queued) queued, SUM(CASE WHEN failed THEN 1 ELSE 0 END) failed')
            ->groupBy('kind')->get()->keyBy('kind');

        return [
            'totals' => $totals,
            'recent' => DB::table('telegram_sends')->orderByDesc('id')->limit(8)->get(),
            'failedRecent' => DB::table('telegram_sends')->where('failed', true)->where('created_at', '>=', now()->subDay())->count(),
            'kinds' => self::KINDS,
        ];
    }
}
