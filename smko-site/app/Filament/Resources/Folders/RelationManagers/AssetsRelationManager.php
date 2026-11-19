<?php

namespace App\Filament\Resources\Folders\RelationManagers;

use App\Filament\Support\AssetFormFields;
use App\Models\Asset;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class AssetsRelationManager extends RelationManager
{
    /** Re-draw the list when the background uploader finishes a file. */
    #[\Livewire\Attributes\On('smko-uploaded')]
    public function refreshAfterUpload(): void
    {
    }

    protected static string $relationship = 'assets';

    protected static ?string $title = 'Files';

    public function form(Schema $schema): Schema
    {
        return $schema->components(AssetFormFields::fields(withFolder: false))->columns(1);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->reorderable('sort_order')
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->label('Title')->searchable()
                    ->formatStateUsing(fn (Asset $r) => (Asset::ICONS[$r->type] ?? '📄').' '.$r->name),
                TextColumn::make('type')->badge(),
                TextColumn::make('size')->label('Size')->state(fn (Asset $r) => $r->sizeForHumans()),
                TextColumn::make('id')->label('File #')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->headerActions([
                \Filament\Actions\Action::make('upload')
                    ->label('Upload files')
                    ->icon('heroicon-o-arrow-up-tray')
                    // Opens the background upload queue (bottom-left); you can keep working while it runs.
                    ->alpineClickHandler(fn () => 'window.dispatchEvent(new CustomEvent(\'smko-upload\', { detail: '
                        .\Illuminate\Support\Js::from(['folderId' => $this->getOwnerRecord()->id, 'path' => $this->getOwnerRecord()->path]).' }))'),
                CreateAction::make()->label('Add file')
                    ->using(fn (array $data) => AssetFormFields::save(
                        $data + ['sort_order' => (int) $this->getOwnerRecord()->assets()->max('sort_order') + 1],
                        folderId: $this->getOwnerRecord()->id,
                    )),
            ])
            ->recordActions([
                AssetFormFields::tempLinkAction(),
                EditAction::make()->using(fn (Model $record, array $data) => AssetFormFields::save($data, $record)),
                \Filament\Actions\ActionGroup::make([
                    ...\App\Filament\Support\CopyMoveActions::assetRow(),
                    DeleteAction::make()
                        ->modalDescription('Removes the file from this site. The object stays in the bucket.'),
                ]),
            ])
            ->toolbarActions([
                \Filament\Actions\BulkActionGroup::make([
                    ...\App\Filament\Support\CopyMoveActions::assetBulk(),
                    \Filament\Actions\DeleteBulkAction::make()->modalDescription('Removes the selected files from this site. The objects stay in the bucket.'),
                ]),
            ]);
    }
}
