<?php

namespace App\Filament\Resources\Folders\Tables;

use App\Models\Folder;
use App\Support\FolderTree;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class FoldersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->description(fn (Folder $r) => $r->parent_id ? FolderTree::path($r->parent_id) : null),
                TextColumn::make('children_count')->counts('children')->label('Folders')->sortable(),
                TextColumn::make('assets_count')->counts('assets')->label('Files')->sortable(),
                TextColumn::make('users_count')->counts('users')->label('Direct access')->sortable(),
                TextColumn::make('sort_order')->label('Position')->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('top')
                    ->label('Level')
                    ->placeholder('All folders')
                    ->trueLabel('Top level only')
                    ->falseLabel('Sub-folders only')
                    ->default(true)
                    ->queries(
                        true: fn (Builder $q) => $q->whereNull('parent_id'),
                        false: fn (Builder $q) => $q->whereNotNull('parent_id'),
                    ),
            ])
            ->recordActions([
                EditAction::make()->label('Open'),
                \Filament\Actions\ActionGroup::make(\App\Filament\Support\CopyMoveActions::folderRow()),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make(\App\Filament\Support\CopyMoveActions::folderBulk()),
            ])
            ->recordUrl(fn (Folder $r) => route('filament.admin.resources.folders.edit', $r));
    }
}
