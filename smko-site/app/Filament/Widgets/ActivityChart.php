<?php

namespace App\Filament\Widgets;

use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class ActivityChart extends ChartWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 8;

    protected ?string $heading = 'Activity — last 30 days';

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '260px';

    protected function getData(): array
    {
        $days = collect(range(29, 0))->map(fn ($d) => now()->subDays($d)->toDateString());
        $rows = DB::table('asset_events')->where('created_at', '>=', now()->subDays(30)->startOfDay())
            ->selectRaw('DATE(created_at) as d, type, COUNT(*) as c')->groupBy('d', 'type')->get()
            ->groupBy('type')->map(fn ($g) => $g->pluck('c', 'd'));
        $logins = DB::table('users')->where('last_login_at', '>=', now()->subDays(30)->startOfDay())
            ->selectRaw('DATE(last_login_at) as d, COUNT(*) as c')->groupBy('d')->pluck('c', 'd');
        $series = fn ($map) => $days->map(fn ($d) => (int) ($map[$d] ?? 0))->all();

        return [
            'datasets' => [
                ['label' => 'Views', 'data' => $series($rows['view'] ?? []), 'borderColor' => '#6366f1', 'backgroundColor' => 'rgba(99,102,241,.15)', 'fill' => true, 'tension' => .3],
                ['label' => 'Downloads', 'data' => $series($rows['download'] ?? []), 'borderColor' => '#10b981', 'backgroundColor' => 'transparent', 'tension' => .3],
                ['label' => 'Telegram', 'data' => $series($rows['telegram'] ?? []), 'borderColor' => '#0ea5e9', 'backgroundColor' => 'transparent', 'tension' => .3],
            ],
            'labels' => $days->map(fn ($d) => date('M j', strtotime($d)))->all(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
