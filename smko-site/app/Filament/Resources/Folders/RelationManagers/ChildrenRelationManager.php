<?php

namespace App\Filament\Resources\Folders\RelationManagers;

use App\Models\Folder;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ChildrenRelationManager extends RelationManager
{
    protected static string $relationship = 'children';

    protected static ?string $title = 'Sub-folders';

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
        ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->searchable()->icon('heroicon-o-folder'),
                TextColumn::make('children_count')->counts('children')->label('Folders'),
                TextColumn::make('assets_count')->counts('assets')->label('Files'),
            ])
            ->headerActions([
                CreateAction::make()->label('New sub-folder')
                    ->mutateDataUsing(fn (array $data) => $data + ['sort_order' => (int) $this->getOwnerRecord()->children()->max('sort_order') + 1]),
            ])
            ->recordActions([
                Action::make('open')->label('Open')->icon('heroicon-o-arrow-right')
                    ->url(fn (Folder $r) => route('filament.admin.resources.folders.edit', $r)),
                \Filament\Actions\ActionGroup::make([
                    ...\App\Filament\Support\CopyMoveActions::folderRow(),
                    DeleteAction::make()
                        ->modalDescription('This deletes the folder with ALL its sub-folders and files. Files are also removed from this site (the bucket keeps them).'),
                ]),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    ...\App\Filament\Support\CopyMoveActions::folderBulk(),
                    \Filament\Actions\DeleteBulkAction::make()->modalDescription('Deletes the selected folders with everything inside (the bucket keeps the files).'),
                ]),
            ])
            ->recordUrl(fn (Folder $r) => route('filament.admin.resources.folders.edit', $r));
    }
}
