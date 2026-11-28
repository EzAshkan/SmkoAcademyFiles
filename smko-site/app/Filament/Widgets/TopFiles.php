<?php

namespace App\Filament\Widgets;

use App\Models\Asset;
use App\Support\FolderTree;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class TopFiles extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 9;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Most used files — last 30 days';

    public function table(Table $table): Table
    {
        $since = now()->subDays(30);

        return $table
            ->query(fn (): Builder => Asset::query()
                ->withCount([
                    'events as views' => fn ($q) => $q->where('type', 'view')->where('created_at', '>=', $since),
                    'events as downloads' => fn ($q) => $q->whereIn('type', ['download', 'telegram'])->where('created_at', '>=', $since),
                ])
                ->whereHas('events', fn ($q) => $q->where('created_at', '>=', $since))
                ->orderByDesc('downloads')->orderByDesc('views'))
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5)
            ->columns([
                TextColumn::make('name')->label('File')->wrap()
                    ->description(fn (Asset $r) => FolderTree::path($r->folder_id, ' › ')),
                TextColumn::make('views')->label('Views'),
                TextColumn::make('downloads')->label('Downloads'),
            ])
            ->emptyStateHeading('No activity yet');
    }
}
