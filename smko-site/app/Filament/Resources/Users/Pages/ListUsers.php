<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\AdminLog;
use App\Support\StudentImporter;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;

class ListUsers extends ListRecords
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('template')
                ->label('Excel template')
                ->icon('heroicon-o-document-arrow-down')
                ->color('gray')
                ->action(function () {
                    $path = storage_path('app/students-template.xlsx');
                    StudentImporter::writeTemplate($path);

                    return response()->download($path, 'smko-students-template.xlsx')->deleteFileAfterSend();
                }),
            Action::make('import')
                ->label('Import from Excel')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->modalDescription('Columns: name, phone, telegram_id, folders, access_ends. Folders are full paths separated by ";" (e.g. "Adults / E4E1; Teens"). Existing students (same phone) keep their password and get the folders added; new students start with "smko".')
                ->schema([
                    FileUpload::make('file')
                        ->label('Excel or CSV file')
                        ->disk('local')
                        ->directory('imports')
                        ->acceptedFileTypes([
                            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                            'text/csv', 'text/plain', 'application/vnd.ms-excel',
                        ])
                        ->preserveFilenames()
                        ->required(),
                    Toggle::make('dry_run')->label('Check only (don\'t save anything)')->default(true),
                ])
                ->action(function (array $data) {
                    $path = Storage::disk('local')->path($data['file']);
                    $r = StudentImporter::import($path, (bool) $data['dry_run']);
                    Storage::disk('local')->delete($data['file']);

                    $summary = "{$r['rows']} rows · {$r['created']} new · {$r['updated']} updated · {$r['grants']} folder grants";
                    $errors = collect($r['errors'])->map(fn ($e, $line) => "Row {$line}: {$e}")->take(15)->implode("\n");

                    if (! $data['dry_run']) {
                        AdminLog::add('imported', 'student', null, "Imported students: {$summary}");
                    }

                    Notification::make()
                        ->title(($data['dry_run'] ? 'Check finished (nothing saved): ' : 'Import finished: ').$summary)
                        ->body($errors ? count($r['errors'])." rows skipped:\n".$errors : 'No problems found.')
                        ->color($r['errors'] ? 'warning' : 'success')
                        ->persistent()
                        ->send();
                }),
            CreateAction::make(),
        ];
    }
}
