<?php

namespace App\Filament\Resources\Announcements;

use App\Filament\Resources\Announcements\Pages\CreateAnnouncement;
use App\Filament\Resources\Announcements\Pages\EditAnnouncement;
use App\Filament\Resources\Announcements\Pages\ListAnnouncements;
use App\Jobs\SendAnnouncement;
use App\Models\Announcement;
use App\Support\FolderTree;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static ?int $navigationSort = 4;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Message')->schema([
                TextInput::make('title')->required()->maxLength(200),
                Textarea::make('body')->label('Details (optional)')->rows(4)->maxLength(3500),
                Select::make('style')->options(Announcement::STYLES)->default('info')->required(),
            ]),
            Section::make('Who sees it')->visible(fn () => \App\Support\Mode::hasStudents())->schema([
                Select::make('folder_ids')
                    ->label('Students with access to')
                    ->helperText('Leave empty to reach every student.')
                    ->multiple()
                    ->searchable()
                    ->options(fn () => FolderTree::options()),
            ]),
            Section::make('Where')->columns(2)->schema([
                Toggle::make('show_banner')->label('Show as a banner on the site')->default(true)->live(),
                Toggle::make('send_telegram')->label('Send as a Telegram message')
                    ->visible(fn () => \App\Support\Mode::hasStudents())
                    ->helperText('Sent once, right after saving.')->default(false)
                    ->disabled(fn (?Announcement $record) => (bool) $record?->telegram_sent_at),
                DateTimePicker::make('starts_at')->label('Banner from')->native(false)->seconds(false)
                    ->visible(fn (Get $get) => $get('show_banner')),
                DateTimePicker::make('ends_at')->label('Banner until')->native(false)->seconds(false)
                    ->default(now()->addDays(7)->endOfDay())->visible(fn (Get $get) => $get('show_banner')),
            ]),
        ])->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('title')->searchable()->wrap()
                    ->description(fn (Announcement $r) => $r->isForEveryone() ? 'Everyone' : collect($r->folder_ids)->map(fn ($id) => FolderTree::path((int) $id))->implode(', ')),
                TextColumn::make('style')->badge()
                    ->color(fn ($state) => ['info' => 'info', 'success' => 'success', 'warning' => 'warning', 'danger' => 'danger'][$state] ?? 'gray'),
                IconColumn::make('show_banner')->label('Banner')->boolean(),
                TextColumn::make('ends_at')->label('Banner until')->dateTime('M j, H:i')->placeholder('—'),
                TextColumn::make('telegram_sent_count')->label('Telegram')
                    ->formatStateUsing(fn (Announcement $r) => $r->telegram_sent_at ? "Sent to {$r->telegram_sent_count}" : ($r->send_telegram ? 'Sending…' : '—')),
                TextColumn::make('created_at')->label('Created')->since(),
            ])
            ->recordActions([
                Action::make('sendTelegram')
                    ->label('Send on Telegram')
                    ->icon('heroicon-o-paper-airplane')
                    ->visible(fn (Announcement $r) => \App\Support\Mode::hasStudents() && ! $r->telegram_sent_at)
                    ->requiresConfirmation()
                    ->modalDescription(fn (Announcement $r) => 'This sends the announcement to '.$r->audience()->filter(fn ($u) => $u->telegramChatId())->count().' students on Telegram. It cannot be undone.')
                    ->action(function (Announcement $r) {
                        $r->update(['send_telegram' => true]);
                        SendAnnouncement::dispatch($r->id);
                        Notification::make()->title('Sending on Telegram')->success()->send();
                    }),
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAnnouncements::route('/'),
            'create' => CreateAnnouncement::route('/create'),
            'edit' => EditAnnouncement::route('/{record}/edit'),
        ];
    }
}
