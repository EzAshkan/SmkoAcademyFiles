<?php

namespace App\Filament\Resources\AdminLogs;

use App\Filament\Resources\AdminLogs\Pages\ListAdminLogs;
use App\Models\AdminLog;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/** Read-only list of changes admins made. */
class AdminLogResource extends Resource
{
    protected static ?string $model = AdminLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $navigationLabel = 'Activity log';

    protected static ?string $modelLabel = 'activity';

    protected static ?int $navigationSort = 9;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('When')->dateTime('M j, Y H:i')->sortable()
                    ->description(fn (AdminLog $r) => $r->created_at->diffForHumans()),
                TextColumn::make('user.name')->label('Admin')->placeholder('System'),
                TextColumn::make('action')->badge()->color(fn ($state) => match ($state) {
                    'deleted' => 'danger', 'created', 'uploaded', 'imported' => 'success', 'granted' => 'info', default => 'gray',
                }),
                TextColumn::make('summary')->wrap()->searchable(),
            ])
            ->filters([
                SelectFilter::make('subject_type')->label('About')->options([
                    'folder' => 'Folders', 'file' => 'Files', 'student' => 'Students', 'access' => 'Access', 'announcement' => 'Announcements',
                ]),
                SelectFilter::make('action')->options([
                    'created' => 'Created', 'updated' => 'Updated', 'deleted' => 'Deleted', 'granted' => 'Access given',
                    'uploaded' => 'Uploaded', 'imported' => 'Imported', 'copied' => 'Copied', 'moved' => 'Moved',
                ]),
                SelectFilter::make('user_id')->label('Admin')->relationship('user', 'name', fn ($query) => $query->where('is_admin', true)),
            ]);
    }

    public static function getPages(): array
    {
        return ['index' => ListAdminLogs::route('/')];
    }
}
