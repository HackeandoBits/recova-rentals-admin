<?php

namespace App\Filament\Resources\Interviews\Pages;

use App\Filament\Resources\Interviews\InterviewResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInterviews extends ListRecords
{
    protected static string $resource = InterviewResource::class;

    use \App\Filament\Traits\PersistsTableConfig;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
