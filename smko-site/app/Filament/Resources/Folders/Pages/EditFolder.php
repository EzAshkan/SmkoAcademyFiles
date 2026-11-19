<?php

namespace App\Filament\Resources\Folders\Pages;

use App\Filament\Resources\Folders\FolderResource;
use App\Support\FolderTree;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFolder extends EditRecord
{
    protected static string $resource = FolderResource::class;

    public function getTitle(): string
    {
        return $this->record->name;
    }

    public function getSubheading(): ?string
    {
        return FolderTree::path($this->record->id, ' › ');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('up')
                ->label('Up one level')
                ->icon('heroicon-o-arrow-up')
                ->color('gray')
                ->url(fn () => $this->record->parent_id
                    ? FolderResource::getUrl('edit', ['record' => $this->record->parent_id])
                    : FolderResource::getUrl('index')),
            Action::make('view')
                ->label('View as student')
                ->icon('heroicon-o-eye')
                ->color('gray')
                ->url(fn () => route('library.folder', $this->record), shouldOpenInNewTab: true),
            DeleteAction::make()
                ->modalDescription('This deletes the folder with ALL its sub-folders and files. Files are also removed from this site (the bucket keeps them).'),
        ];
    }

    protected function getRedirectUrl(): ?string
    {
        return null;
    }
}
