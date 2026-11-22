<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\DB;

class ActivityStats extends StatsOverviewWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected ?string $heading = 'Last 7 days';

    protected function getStats(): array
    {
        $since = now()->subDays(7);
        // Daily counts for the little sparklines (last 14 days), one query per type group.
        $daily = function (string|array $types) {
            $rows = DB::table('asset_events')->whereIn('type', (array) $types)
                ->where('created_at', '>=', now()->subDays(13)->startOfDay())
                ->selectRaw('DATE(created_at) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd');

            return collect(range(13, 0))->map(fn ($n) => (int) ($rows[now()->subDays($n)->toDateString()] ?? 0))->all();
        };

        $students = User::where('is_admin', false)->count();
        $active = User::where('is_admin', false)->where('last_login_at', '>=', $since)->count();
        $changed = User::where('is_admin', false)->where('must_change_password', false)->count();

        $first = \App\Support\Mode::isPublic()
            ? Stat::make('Library', Asset::count().' files')
                ->description(\App\Models\Folder::count().' folders · public — no login needed')->icon('heroicon-o-globe-alt')
            : null;

        return [
            $first ?? Stat::make('Active students', $active)
                ->description("of {$students} logged in · {$changed} have set their own password")
                ->icon('heroicon-o-users'),
            Stat::make('File views', DB::table('asset_events')->where('type', 'view')->where('created_at', '>=', $since)->count())
                ->description('previews opened')->chart($daily('view'))->color('info')->icon('heroicon-o-eye'),
            Stat::make('Downloads', DB::table('asset_events')->whereIn('type', ['download', 'telegram'])->where('created_at', '>=', $since)->count())
                ->description(DB::table('asset_events')->where('type', 'telegram')->where('created_at', '>=', $since)->count().' via Telegram')
                ->chart($daily(['download', 'telegram']))->color('success')->icon('heroicon-o-arrow-down-tray'),
            Stat::make('New files', Asset::where('created_at', '>=', $since)->count())
                ->description(Asset::whereNull('notified_at')->where('notify', true)->count().' waiting to be announced')
                ->icon('heroicon-o-document-plus'),
        ];
    }
}
