<?php

namespace App\Filament\Resources\Folders\Schemas;

use App\Models\Folder;
use App\Support\FolderTree;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FolderForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('parent_id')
                    ->label('Inside folder')
                    ->placeholder('— Top level —')
                    // A folder can't be moved inside itself or its own sub-folders.
                    ->options(fn (?Folder $record) => FolderTree::options($record?->id))
                    ->default(fn () => request()->integer('parent') ?: null)
                    ->searchable(),
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('sort_order')
                    ->label('Position')
                    ->numeric()
                    ->default(0),
            ]);
    }
}
