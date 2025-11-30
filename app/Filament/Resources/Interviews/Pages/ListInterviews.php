<?php

namespace App\Filament\Resources\Interviews\Pages;

use App\Filament\Resources\Interviews\InterviewResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListInterviews extends ListRecords
{
    protected static string $resource = InterviewResource::class;

    use \App\Filament\Traits\PersistsTableConfig;

    public function mountPersistsTableConfig(): void
    {
        // Hydrate filters
        if ($filters = $this->getUserTableSettings('filters')) {
            $this->tableFilters = array_merge($this->tableFilters ?? [], $filters);
        }

        // Hydrate sort
        if ($sortColumn = $this->getUserTableSettings('sort_column')) {
            $this->tableSortColumn = $sortColumn;
        }
        if ($sortDirection = $this->getUserTableSettings('sort_direction')) {
            $this->tableSortDirection = $sortDirection;
        }

        // Hydrate search
        if ($search = $this->getUserTableSettings('search')) {
            $this->tableSearchQuery = $search;
        }

        // Hydrate columns (try all known properties)
        if ($columns = $this->getUserTableSettings('column_visibility')) {
            if (property_exists($this, 'toggledTableColumns')) {
                $this->toggledTableColumns = array_merge($this->toggledTableColumns ?? [], $columns);
            }
            if (property_exists($this, 'toggledHiddenColumns')) {
                $this->toggledHiddenColumns = array_merge($this->toggledHiddenColumns ?? [], $columns);
            }
            if (property_exists($this, 'tableColumnToggledHiddenState')) {
                $this->tableColumnToggledHiddenState = array_merge($this->tableColumnToggledHiddenState ?? [], $columns);
            }
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
