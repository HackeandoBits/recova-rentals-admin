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
        ];
    }
}
