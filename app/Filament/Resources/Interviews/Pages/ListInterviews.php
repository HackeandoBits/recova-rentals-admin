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

            CreateAction::make()
                ->label('Crear Reunión')
                ->model(\App\Models\Interview::class)
                ->form(\App\Filament\Resources\Interviews\Schemas\InterviewForm::schema())
                ->icon('heroicon-o-plus-circle')
                ->modalHeading('Crear Reunión')
                ->modalWidth('4xl')
                ->createAnother(false),

            \Filament\Actions\Action::make('syncGoogle')
                ->label('Sincronizar Google')
                ->color('success')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    $svc = app(\App\Services\GoogleCalendarService::class);
                    // Sincronizar año actual y el siguiente completo
                    $result = $svc->syncFromGoogle(now()->startOfYear(), now()->addYear()->endOfYear());

                    // Compatibilidad si devuelve int (por si acaso) o array
                    $count = is_array($result) ? ($result['count'] ?? 0) : $result;
                    $errors = is_array($result) ? ($result['errors'] ?? []) : [];

                    // Limpiar caché global de fechas bloqueadas
                    \Illuminate\Support\Facades\Cache::forget('blocked_dates_global');

                    if (! empty($errors)) {
                        // Mostrar primer error para no spammear
                        $firstError = $errors[0];
                        $totalErrors = count($errors);

                        \Filament\Notifications\Notification::make()
                            ->title('Hubo errores en la sincronización')
                            ->body("Se encontraron {$count} eventos, pero fallaron {$totalErrors}.<br>Primer error: {$firstError}")
                            ->danger()
                            ->persistent() // Para que el usuario tenga tiempo de leer
                            ->send();
                    } else {
                        \Filament\Notifications\Notification::make()
                            ->title('Sincronización completada')
                            ->body("Se importaron {$count} eventos nuevos (feriados y reuniones). Verificá ambas listas.")
                            ->success()
                            ->send();
                    }
                }),
        ];
    }
}
