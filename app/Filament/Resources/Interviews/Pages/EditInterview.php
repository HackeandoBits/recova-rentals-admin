<?php

namespace App\Filament\Resources\Interviews\Pages;

use App\Filament\Resources\Interviews\InterviewResource;
use Carbon\Carbon;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditInterview extends EditRecord
{
    protected static string $resource = InterviewResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * Poblar los campos separados de fecha/hora al cargar un registro existente
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (isset($data['start_at'])) {
            $start = Carbon::parse($data['start_at']);
            $data['start_date'] = $start->toDateString();
            $data['start_time'] = $start->format('H:i');
        }

        if (isset($data['end_at'])) {
            $end = Carbon::parse($data['end_at']);
            $data['end_date'] = $end->toDateString();
            $data['end_time'] = $end->format('H:i');
        }

        return $data;
    }
}
