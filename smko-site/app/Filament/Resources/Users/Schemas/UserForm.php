<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Models\User;
use App\Support\FolderTree;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rule;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(255),
                TextInput::make('phone')
                    ->label('Username')
                    ->required()
                    ->maxLength(64)
                    ->rule(fn (?User $record) => Rule::unique('users', 'phone')->ignore($record)),
                TextInput::make('telegram_id')->label('Telegram ID')->maxLength(64),
                Select::make('folders')
                    ->label('Folder access')
                    ->helperText('Access to a folder includes everything inside it.')
                    ->multiple()
                    ->relationship('folders', 'name')
                    ->getOptionLabelFromRecordUsing(fn ($record) => FolderTree::path($record->id))
                    ->options(fn () => FolderTree::options())
                    ->searchable()
                    ->columnSpanFull(),
                Toggle::make('is_admin')->label('Admin'),
                Placeholder::make('password_info')
                    ->label('Password')
                    ->content(fn (?User $record) => $record
                        ? ($record->must_change_password ? 'Still on the starting password "smko".' : 'Has chosen their own password.')
                        : 'New teachers start with the password "smko" and must change it at first login.'),
            ]);
    }
}
