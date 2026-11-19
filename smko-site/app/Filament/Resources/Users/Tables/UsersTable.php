<?php

namespace App\Filament\Resources\Users\Tables;

use App\Models\User;
use App\Support\FolderTree;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('phone')->label('Phone / login')->searchable(),
                TextColumn::make('folders_count')->counts('folders')->label('Folders')->sortable(),
                IconColumn::make('must_change_password')->label('Own password')->boolean()
                    ->trueIcon('heroicon-o-clock')->falseIcon('heroicon-o-check-circle')
                    ->trueColor('warning')->falseColor('success')
                    ->tooltip(fn (User $r) => $r->must_change_password ? 'Still on "smko"' : 'Changed'),
                IconColumn::make('is_admin')->label('Admin')->boolean(),
                TextColumn::make('last_login_at')->label('Last login')->since()->sortable()
                    ->placeholder('Never')->tooltip(fn (User $r) => $r->last_login_at?->format('M j, Y H:i')),
                TextColumn::make('login_count')->label('Logins')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('events_count')->counts('events')->label('Files opened')->sortable()->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('telegram_notifications')->label('Telegram alerts')->boolean()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('folder')
                    ->label('Has access to')
                    ->options(fn () => FolderTree::options())
                    ->searchable()
                    ->query(fn (Builder $q, array $data) => $data['value']
                        ? $q->whereHas('folders', fn ($f) => $f->where('folders.id', $data['value']))
                        : $q),
                TernaryFilter::make('must_change_password')->label('Password')
                    ->trueLabel('Still on "smko"')->falseLabel('Changed'),
                TernaryFilter::make('is_admin')->label('Admin'),
                \Filament\Tables\Filters\Filter::make('inactive')->label('Not logged in for 30 days')
                    ->query(fn (Builder $q) => $q->where(fn ($q) => $q->whereNull('last_login_at')->orWhere('last_login_at', '<', now()->subDays(30)))),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('resetPassword')
                    ->label('Reset password')
                    ->icon('heroicon-o-key')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalDescription('Sets the password back to "smko". They must choose a new one at next login.')
                    ->action(function (User $record) {
                        $record->resetToDefaultPassword();
                        Notification::make()->title('Password reset to "smko"')->success()->send();
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('grant')
                        ->label('Give access to folder')
                        ->icon('heroicon-o-lock-open')
                        ->schema([
                            Select::make('folder_id')->label('Folder')->options(fn () => FolderTree::options())->searchable()->required(),
                            \App\Filament\Resources\Folders\RelationManagers\UsersRelationManager::expiryField(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $u) {
                                $u->folders()->syncWithoutDetaching([$data['folder_id'] => [
                                    'expires_at' => $data['expires_at'] ?? null, 'granted_at' => now(),
                                ]]);
                            }
                            \App\Models\AdminLog::add('granted', 'access', (int) $data['folder_id'],
                                'Gave access to '.FolderTree::path((int) $data['folder_id']).' for '.$records->count().' student(s)'
                                .(! empty($data['expires_at']) ? ' until '.$data['expires_at'] : ''));
                            Notification::make()->title('Access given to '.$records->count().' students')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('revoke')
                        ->label('Remove access to folder')
                        ->icon('heroicon-o-lock-closed')
                        ->color('danger')
                        ->schema([Select::make('folder_id')->label('Folder')->options(fn () => FolderTree::options())->searchable()->required()])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $u) {
                                $u->folders()->detach($data['folder_id']);
                            }
                            Notification::make()->title('Access removed for '.$records->count().' students')->success()->send();
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
