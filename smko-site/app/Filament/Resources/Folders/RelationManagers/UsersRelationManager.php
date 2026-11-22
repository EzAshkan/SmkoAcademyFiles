<?php

namespace App\Filament\Resources\Folders\RelationManagers;

use App\Models\AdminLog;
use Filament\Actions\AttachAction;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DetachAction;
use Filament\Actions\DetachBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class UsersRelationManager extends RelationManager
{
    protected static string $relationship = 'users';

    protected static ?string $title = 'Access';

    /** Public mode: everything is open, so there's no access list. */
    public static function canViewForRecord(\Illuminate\Database\Eloquent\Model $ownerRecord, string $pageClass): bool
    {
        return \App\Support\Mode::hasStudents();
    }

    public static function expiryField(): DateTimePicker
    {
        return DateTimePicker::make('expires_at')
            ->label('Access ends')
            ->helperText('Leave empty for no end date.')
            ->native(false)
            ->seconds(false)
            ->minDate(now()->startOfDay());
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([static::expiryField()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->description('Students listed here can open this folder and everything inside it, until their access ends.')
            ->columns([
                TextColumn::make('name')->searchable(),
                TextColumn::make('phone')->searchable(),
                TextColumn::make('expires_at')
                    ->label('Access ends')
                    ->dateTime('M j, Y H:i')
                    ->sortable()
                    ->placeholder('No end date')
                    ->badge()
                    ->color(fn ($state) => ! $state ? 'gray' : (now()->gte($state) ? 'danger' : (now()->addDays(7)->gte($state) ? 'warning' : 'success')))
                    ->formatStateUsing(fn ($state) => now()->gte($state) ? 'Expired '.$state->format('M j') : $state->format('M j, Y H:i')),
            ])
            ->filters([
                TernaryFilter::make('expired')
                    ->label('Status')
                    ->placeholder('All')
                    ->trueLabel('Expired')
                    ->falseLabel('Active')
                    ->queries(
                        true: fn (Builder $q) => $q->wherePivot('expires_at', '<=', now()),
                        false: fn (Builder $q) => $q->where(fn ($q) => $q->whereNull('folder_user.expires_at')->orWhere('folder_user.expires_at', '>', now())),
                    ),
            ])
            ->headerActions([
                AttachAction::make()
                    ->label('Give access')
                    ->multiple()
                    ->preloadRecordSelect()
                    ->recordSelectSearchColumns(['name', 'phone'])
                    ->recordTitle(fn ($record) => "{$record->name} ({$record->phone})")
                    ->schema(fn (AttachAction $action) => [$action->getRecordSelect(), static::expiryField()])
                    ->mutateDataUsing(fn (array $data) => $data + ['granted_at' => now()])
                    ->after(fn (array $data) => AdminLog::add('granted', 'access', $this->getOwnerRecord()->id,
                        'Gave access to '.$this->getOwnerRecord()->path.' for '.count((array) $data['recordId']).' student(s)'
                        .(! empty($data['expires_at']) ? ' until '.$data['expires_at'] : ''))),
            ])
            ->recordActions([
                EditAction::make()->label('End date'),
                DetachAction::make()->label('Remove access'),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('setExpiry')
                        ->label('Set end date')
                        ->icon('heroicon-o-calendar')
                        ->schema([static::expiryField()])
                        ->action(function (Collection $records, array $data) {
                            foreach ($records as $u) {
                                $this->getOwnerRecord()->users()->updateExistingPivot($u->id, ['expires_at' => $data['expires_at'] ?? null]);
                            }
                        })
                        ->deselectRecordsAfterCompletion(),
                    DetachBulkAction::make()->label('Remove access'),
                ]),
            ]);
    }
}
