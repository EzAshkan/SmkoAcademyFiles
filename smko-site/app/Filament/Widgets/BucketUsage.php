<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Cache;

/** How much of the ParsPack bucket this site's files use (from the library, refreshed every 10 min). */
class BucketUsage extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 6;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.bucket-usage';

    protected function getViewData(): array
    {
        return Cache::remember('dashboard:bucket-usage', 600, function () {
            $rows = Asset::query()->whereNotNull('file_ext')
                ->get(['id', 'type', 'file_ext', 'storage_key', 'size_bytes', 'created_at']);

            // Several entries can share one stored object (copies / reused uploads): count it once.
            $objects = [];
            $byType = [];
            $shared = 0;
            $savedBytes = 0;
            foreach ($rows as $a) {
                $key = $a->storageKey();
                $size = (int) $a->size_bytes;
                if (isset($objects[$key])) {
                    $shared++;
                    $savedBytes += $size;

                    continue;
                }
                $objects[$key] = $size;
                $byType[$a->type] = ($byType[$a->type] ?? 0) + $size;
            }
            arsort($byType);

            $months = collect(range(5, 0))->map(function ($n) use ($rows) {
                $start = now()->subMonths($n)->startOfMonth();
                $end = $start->copy()->endOfMonth();
                $in = $rows->filter(fn ($a) => $a->created_at && $a->created_at->between($start, $end));

                return ['label' => $start->format('M'), 'bytes' => (int) $in->sum('size_bytes'), 'files' => $in->count()];
            });

            return [
                'total' => array_sum($objects),
                'objects' => count($objects),
                'entries' => $rows->count(),
                'shared' => $shared,
                'saved' => $savedBytes,
                'byType' => $byType,
                'months' => $months,
                'monthMax' => max(1, $months->max('bytes')),
                'updated' => now(),
            ];
        });
    }
}
