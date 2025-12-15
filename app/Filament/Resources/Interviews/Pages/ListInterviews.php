<?php

namespace App\Filament\Resources\Interviews\Pages;

use App\Filament\Resources\Interviews\InterviewResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInterviews extends ListRecords
{
    use \App\Filament\Traits\PersistsTableConfig;

    protected static string $resource = InterviewResource::class;

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('syncGoogle')
                ->label('Sincronizar Google')
                ->color('info')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $svc = app(\App\Services\GoogleCalendarService::class);
                    $count = $svc->syncFromGoogle(now()->subMonth(), now()->addMonths(3));

                    \Filament\Notifications\Notification::make()
                        ->title('Sincronización completada')
                        ->body("Se importaron {$count} eventos nuevos como bloqueos.")
                        ->success()
                        ->send();
                }),
            CreateAction::make()
                ->label('Crear Reunión')
                ->model(\App\Models\Interview::class)
                ->form(\App\Filament\Resources\Interviews\Schemas\InterviewForm::schema())
                ->icon('heroicon-o-plus-circle')
                ->modalHeading('Crear Reunión')
                ->modalWidth('4xl')
                ->createAnother(false),
        ];
    }
}
