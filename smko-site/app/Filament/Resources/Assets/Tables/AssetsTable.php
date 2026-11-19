<?php

namespace App\Filament\Resources\Assets\Tables;

use App\Filament\Support\AssetFormFields;
use App\Models\Asset;
use App\Support\FolderTree;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AssetsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('name')->label('Title')->searchable()->wrap()
                    ->formatStateUsing(fn (Asset $r) => (Asset::ICONS[$r->type] ?? '📄').' '.$r->name)
                    ->description(fn (Asset $r) => FolderTree::path($r->folder_id)),
                TextColumn::make('type')->badge(),
                TextColumn::make('size')->state(fn (Asset $r) => $r->sizeForHumans()),
                TextColumn::make('created_at')->label('Added')->date()->sortable()->toggleable(),
                TextColumn::make('views_count')->label('Views')->sortable()->toggleable()
                    ->counts(['events as views_count' => fn ($q) => $q->where('type', 'view')]),
                TextColumn::make('downloads_count')->label('Downloads')->sortable()->toggleable()
                    ->counts(['events as downloads_count' => fn ($q) => $q->whereIn('type', ['download', 'telegram'])]),
            ])
            ->filters([
                SelectFilter::make('type')->options(array_combine(Asset::TYPES, Asset::TYPES)),
                SelectFilter::make('folder')
                    ->label('In folder (incl. sub-folders)')
                    ->options(fn () => FolderTree::options())
                    ->searchable()
                    ->query(function (Builder $q, array $data) {
                        if (! $data['value']) {
                            return $q;
                        }
                        $root = (int) $data['value'];
                        $ids = array_keys(array_filter(
                            FolderTree::nodes(),
                            fn ($n) => in_array($root, FolderTree::lineage($n['id']), true)
                        ));

                        return $q->whereIn('folder_id', $ids);
                    }),
                TernaryFilter::make('in_bucket')
                    ->label('File in bucket')
                    ->trueLabel('Matched')
                    ->falseLabel('Not matched yet')
                    ->queries(
                        true: fn (Builder $q) => $q->whereNotNull('file_ext'),
                        false: fn (Builder $q) => $q->whereNull('file_ext')->where('type', '!=', 'TEXT'),
                    ),
            ])
            ->recordActions([
                AssetFormFields::tempLinkAction(),
                EditAction::make()->using(fn (\Illuminate\Database\Eloquent\Model $record, array $data) => AssetFormFields::save($data, $record)),
                \Filament\Actions\ActionGroup::make(\App\Filament\Support\CopyMoveActions::assetRow()),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make(\App\Filament\Support\CopyMoveActions::assetBulk()),
            ]);
    }
}
