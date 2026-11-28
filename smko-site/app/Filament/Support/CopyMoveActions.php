<?php

namespace App\Filament\Support;

use App\Support\FolderTree;
use App\Support\LibraryOps;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/** "Copy to…" / "Move to…" actions for files and folders (single and multi-select). */
class CopyMoveActions
{
    protected static function folderSelect(bool $allowTop, ?Collection $exclude = null): Select
    {
        return Select::make('target')
            ->label('Destination folder')
            ->searchable()
            ->required(! $allowTop)
            ->placeholder($allowTop ? '— Top level —' : 'Choose a folder')
            ->options(function () use ($exclude) {
                $opts = FolderTree::options();
                // Can't put a folder inside itself.
                foreach ($exclude ?? [] as $id) {
                    foreach (array_keys($opts) as $oid) {
                        if (in_array($id, FolderTree::lineage($oid), true)) {
                            unset($opts[$oid]);
                        }
                    }
                }

                return $opts;
            });
    }

    protected static function run(callable $fn, string $doneTitle): void
    {
        try {
            $msg = $fn();
            Notification::make()->title($doneTitle)->body($msg)->success()->send();
        } catch (\InvalidArgumentException $e) {
            Notification::make()->title($e->getMessage())->danger()->send();
        }
    }

    /** @return BulkAction[] */
    public static function assetBulk(): array
    {
        return [
            BulkAction::make('moveAssets')->label('Move to…')->icon('heroicon-o-arrow-right-circle')
                ->schema([static::folderSelect(false)])
                ->action(fn (Collection $records, array $data) => static::run(
                    fn () => LibraryOps::moveAssets($records->pluck('id')->all(), (int) $data['target']).' file(s) moved to '.FolderTree::path((int) $data['target']),
                    'Moved'))
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('copyAssets')->label('Copy to…')->icon('heroicon-o-document-duplicate')
                ->modalDescription('Copies use the same stored file — nothing is uploaded again.')
                ->schema([static::folderSelect(false)])
                ->action(fn (Collection $records, array $data) => static::run(
                    fn () => LibraryOps::copyAssets($records->pluck('id')->all(), (int) $data['target']).' file(s) copied to '.FolderTree::path((int) $data['target']),
                    'Copied'))
                ->deselectRecordsAfterCompletion(),
        ];
    }

    /** @return Action[] */
    public static function assetRow(): array
    {
        return [
            Action::make('moveAsset')->label('Move')->icon('heroicon-o-arrow-right-circle')
                ->schema([static::folderSelect(false)])
                ->action(fn (Model $record, array $data) => static::run(
                    fn () => LibraryOps::moveAssets([$record->id], (int) $data['target']) ? 'Now in '.FolderTree::path((int) $data['target']) : 'Already there', 'Moved')),
            Action::make('copyAsset')->label('Copy')->icon('heroicon-o-document-duplicate')
                ->schema([static::folderSelect(false)])
                ->action(fn (Model $record, array $data) => static::run(
                    fn () => LibraryOps::copyAssets([$record->id], (int) $data['target']) ? 'Copy added to '.FolderTree::path((int) $data['target']) : '', 'Copied')),
        ];
    }

    /** @return BulkAction[] */
    public static function folderBulk(): array
    {
        return [
            BulkAction::make('moveFolders')->label('Move to…')->icon('heroicon-o-arrow-right-circle')
                ->schema(fn (Collection $records) => [static::folderSelect(true, $records->pluck('id'))])
                ->action(fn (Collection $records, array $data) => static::run(
                    fn () => LibraryOps::moveFolders($records->pluck('id')->all(), $data['target'] ? (int) $data['target'] : null).' folder(s) moved',
                    'Moved'))
                ->deselectRecordsAfterCompletion(),
            BulkAction::make('copyFolders')->label('Copy to…')->icon('heroicon-o-document-duplicate')
                ->modalDescription('Copies the folders with everything inside. Files reuse the stored files — nothing is uploaded again. Teacher access is not copied.')
                ->schema(fn (Collection $records) => [static::folderSelect(true, $records->pluck('id'))])
                ->action(fn (Collection $records, array $data) => static::run(function () use ($records, $data) {
                    $c = LibraryOps::copyFolders($records->pluck('id')->all(), $data['target'] ? (int) $data['target'] : null);

                    return "{$c['folders']} folder(s) and {$c['files']} file(s) copied";
                }, 'Copied'))
                ->deselectRecordsAfterCompletion(),
        ];
    }

    /** @return Action[] */
    public static function folderRow(): array
    {
        return [
            Action::make('moveFolder')->label('Move')->icon('heroicon-o-arrow-right-circle')
                ->schema(fn (Model $record) => [static::folderSelect(true, collect([$record->id]))])
                ->action(fn (Model $record, array $data) => static::run(
                    fn () => LibraryOps::moveFolders([$record->id], $data['target'] ? (int) $data['target'] : null) ? 'Now in '.($data['target'] ? FolderTree::path((int) $data['target']) : 'top level') : 'Already there',
                    'Moved')),
            Action::make('copyFolder')->label('Copy')->icon('heroicon-o-document-duplicate')
                ->schema(fn (Model $record) => [static::folderSelect(true, collect([$record->id]))])
                ->action(fn (Model $record, array $data) => static::run(function () use ($record, $data) {
                    $c = LibraryOps::copyFolders([$record->id], $data['target'] ? (int) $data['target'] : null);

                    return "{$c['folders']} folder(s) and {$c['files']} file(s) copied";
                }, 'Copied')),
        ];
    }
}
