<?php

namespace App\Filament\Resources\Announcements\Pages;

use App\Filament\Resources\Announcements\AnnouncementResource;
use App\Jobs\SendAnnouncement;
use App\Models\AdminLog;
use Filament\Resources\Pages\CreateRecord;

class CreateAnnouncement extends CreateRecord
{
    protected static string $resource = AnnouncementResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return $data + ['created_by' => auth()->id()];
    }

    protected function afterCreate(): void
    {
        AdminLog::add('created', 'announcement', $this->record->id, 'Announcement: '.$this->record->title);
        if ($this->record->send_telegram) {
            SendAnnouncement::dispatch($this->record->id);
        }
    }
}
