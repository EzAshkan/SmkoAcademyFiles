<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Support\FolderTree;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\DB;

/** Data streamed through this server (downloads and previews). */
class TrafficOverview extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 5;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.traffic-overview';

    public static function gb(int|float $bytes): string
    {
        return $bytes >= 1073741824 ? number_format($bytes / 1073741824, 2).' GB' : number_format($bytes / 1048576, 1).' MB';
    }

    protected function getViewData(): array
    {
        $sum = fn ($from) => DB::table('traffic_daily')->where('day', '>=', $from)
            ->selectRaw('COALESCE(SUM(bytes),0) b, COALESCE(SUM(hits),0) h')->first();
        $today = $sum(now()->toDateString());
        $week = $sum(now()->subDays(6)->toDateString());
        $month = $sum(now()->subDays(29)->toDateString());

        $byDay = DB::table('traffic_daily')->where('day', '>=', now()->subDays(29)->toDateString())
            ->selectRaw('day, kind, SUM(bytes) b')->groupBy('day', 'kind')->get()
            ->groupBy(fn ($r) => (string) $r->day);
        $days = collect(range(29, 0))->map(function ($n) use ($byDay) {
            $d = now()->subDays($n)->toDateString();
            $rows = $byDay[$d] ?? collect();

            return ['day' => $d, 'download' => (int) $rows->where('kind', 'download')->sum('b'), 'preview' => (int) $rows->where('kind', 'preview')->sum('b')];
        });
        $max = max(1, $days->max(fn ($d) => $d['download'] + $d['preview']));

        $top = DB::table('traffic_daily')->where('day', '>=', now()->subDays(29)->toDateString())
            ->whereNotNull('asset_id')->selectRaw('asset_id, SUM(bytes) b, SUM(hits) h')
            ->groupBy('asset_id')->orderByDesc('b')->limit(6)->get();
        $assets = Asset::whereIn('id', $top->pluck('asset_id'))->get()->keyBy('id');

        return [
            'today' => $today, 'week' => $week, 'month' => $month,
            'days' => $days, 'max' => $max,
            'top' => $top->map(fn ($r) => [
                'name' => $assets[$r->asset_id]->name ?? '(deleted file)',
                'path' => isset($assets[$r->asset_id]) ? FolderTree::path($assets[$r->asset_id]->folder_id, ' › ') : '',
                'bytes' => (int) $r->b, 'hits' => (int) $r->h,
            ]),
        ];
    }
}
