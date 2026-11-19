<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['password'] = User::DEFAULT_PASSWORD;
        $data['must_change_password'] = true;
        $data['telegram_id'] = ($data['telegram_id'] ?? null) ?: $data['phone'];

        return $data;
    }
}
