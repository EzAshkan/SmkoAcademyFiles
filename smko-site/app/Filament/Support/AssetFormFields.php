<?php

namespace App\Filament\Support;

use App\Models\Asset;
use App\Support\AssetFiles;
use App\Support\FolderTree;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Facades\URL;

/** Form fields and actions shared by the Files page and a folder's Files tab. */
class AssetFormFields
{
    public static function fields(bool $withFolder = true): array
    {
        return array_values(array_filter([
            $withFolder ? Select::make('folder_id')
                ->label('Folder')
                ->options(fn () => FolderTree::options())
                ->searchable()
                ->required() : null,
            TextInput::make('name')->label('Title')->required()->maxLength(255),
            Select::make('type')
                ->options(array_combine(Asset::TYPES, array_map(fn ($t) => ucfirst(strtolower($t)), Asset::TYPES)))
                ->required()
                ->live()
                ->default('DOCUMENT'),
            FileUpload::make('upload')
                ->label(fn (?Asset $record) => $record?->hasFile() ? 'Replace file (optional)' : 'File')
                ->disk(AssetFiles::TEMP_DISK)
                ->directory(AssetFiles::TEMP_DIR)
                ->preserveFilenames()
                ->storeFileNamesIn('upload_name')
                ->maxSize(2 * 1024 * 1024) // 2 GB, the Telegram bot's limit
                ->hidden(fn (Get $get) => $get('type') === 'TEXT')
                ->required(fn (Get $get, ?Asset $record) => $get('type') !== 'TEXT' && ! $record?->hasFile())
                ->dehydrated(),
            Textarea::make('text')
                ->label('Text')
                ->rows(8)
                ->visible(fn (Get $get) => $get('type') === 'TEXT')
                ->required(fn (Get $get) => $get('type') === 'TEXT'),
            Textarea::make('description')->rows(3),
            TextInput::make('sort_order')->numeric()->default(0)->label('Position'),
            \Filament\Forms\Components\Toggle::make('notify')
                ->label('Tell students on Telegram')
                ->helperText('Students who can open this folder get a message a few minutes after upload.')
                ->default(false)
                ->visible(fn (?Asset $record) => \App\Support\Mode::hasStudents() && (! $record || ! $record->notified_at)),
        ]));
    }

    /** Pull the upload out of the form data, save the record, then move the file to the bucket. */
    public static function save(array $data, ?Asset $asset = null, ?int $folderId = null): Asset
    {
        $upload = $data['upload'] ?? null;
        $uploadName = $data['upload_name'] ?? null;
        unset($data['upload'], $data['upload_name']);
        if (is_array($upload)) {
            $upload = array_values($upload)[0] ?? null;
        }
        if (is_array($uploadName)) {
            $uploadName = array_values($uploadName)[0] ?? null;
        }
        if ($folderId) {
            $data['folder_id'] = $folderId;
        }

        $asset ??= new Asset;
        $asset->fill($data)->save();
        AssetFiles::attach($asset, $upload, $uploadName);

        return $asset->refresh();
    }

    public static function tempLinkAction(): Action
    {
        return Action::make('tempLink')
            ->label('Temp link')
            ->icon('heroicon-o-link')
            ->visible(fn (Asset $record) => $record->hasFile())
            ->schema([
                Select::make('minutes')->label('Valid for')->required()->default(15)->options([
                    15 => '15 minutes', 30 => '30 minutes', 60 => '1 hour', 180 => '3 hours', 360 => '6 hours',
                    720 => '12 hours', 1440 => '1 day', 4320 => '3 days', 10080 => '7 days',
                ]),
            ])
            ->action(function (Asset $record, array $data) {
                $url = URL::temporarySignedRoute('assets.share', now()->addMinutes((int) $data['minutes']), ['asset' => $record->id]);
                Notification::make()
                    ->title('Temporary link ready')
                    ->body($url)
                    ->success()
                    ->persistent()
                    ->send();
            });
    }
}
