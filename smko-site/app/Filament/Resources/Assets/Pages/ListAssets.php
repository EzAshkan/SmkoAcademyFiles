<?php

namespace App\Filament\Resources\Assets\Pages;

use App\Filament\Resources\Assets\AssetResource;
use App\Filament\Support\AssetFormFields;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAssets extends ListRecords
{
    protected static string $resource = AssetResource::class;

    /** Re-draw the list when the background uploader finishes a file. */
    #[\Livewire\Attributes\On('smko-uploaded')]
    public function refreshAfterUpload(): void
    {
    }

    protected function getHeaderActions(): array
    {
        return [
            // Same background uploader as inside folders; the panel asks which folder first.
            Action::make('upload')
                ->label('Upload files')
                ->icon('heroicon-o-arrow-up-tray')
                ->alpineClickHandler("window.dispatchEvent(new CustomEvent('smko-upload', { detail: {} }))"),
            CreateAction::make()
                ->label('Add file')
                ->color('gray')
                ->modalHeading('Add a file or text post')
                ->using(fn (array $data) => AssetFormFields::save($data)),
        ];
    }
}
