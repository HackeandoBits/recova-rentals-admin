<?php

namespace App\Filament\Resources\CalendarBlocks\Pages;

use App\Filament\Resources\CalendarBlocks\CalendarBlockResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCalendarBlock extends CreateRecord
{
    protected static string $resource = CalendarBlockResource::class;

    protected static ?string $title = 'Crear Bloqueo';

    protected function afterCreate(): void
    {
        // Sincronizar inmediatamente con Google Calendar
        \App\Jobs\SyncSingleBlockJob::dispatchSync($this->record->id);
    }
}
