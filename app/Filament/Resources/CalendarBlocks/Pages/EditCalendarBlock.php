<?php

namespace App\Filament\Resources\CalendarBlocks\Pages;

use App\Filament\Resources\CalendarBlocks\CalendarBlockResource;
use Filament\Resources\Pages\EditRecord;

class EditCalendarBlock extends EditRecord
{
    protected static string $resource = CalendarBlockResource::class;

    protected static ?string $title = 'Editar Bloqueo';

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
