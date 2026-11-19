<?php

namespace App\Filament\Widgets;

use App\Console\Commands\HealthCheck as HealthCommand;
use App\Models\HealthCheck;
use Filament\Widgets\Widget;
use Illuminate\Support\Facades\Artisan;

class SystemHealth extends Widget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.system-health';

    public function runNow(): void
    {
        Artisan::call('smko:health', ['--quiet-alerts' => true]);
    }

    protected function getViewData(): array
    {
        $checks = HealthCheck::all()->keyBy('name');

        return [
            'labels' => HealthCommand::LABELS,
            'checks' => $checks,
            'lastRun' => $checks->max('checked_at'),
        ];
    }
}
