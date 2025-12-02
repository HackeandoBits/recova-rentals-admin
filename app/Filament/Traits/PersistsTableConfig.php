<?php

namespace App\Filament\Traits;

use Filament\Tables\Table;

trait PersistsTableConfig
{
    // --- Load State ---

    public function getTableFiltersFormState(): array
    {
        return $this->getUserTableSettings('filters') ?? parent::getTableFiltersFormState();
    }

    public function getTableSortColumn(): ?string
    {
        return $this->getUserTableSettings('sort_column') ?? parent::getTableSortColumn();
    }

    public function getTableSortDirection(): ?string
    {
        return $this->getUserTableSettings('sort_direction') ?? parent::getTableSortDirection();
    }

    public function getTableSearchQuery(): ?string
    {
        return $this->getUserTableSettings('search') ?? parent::getTableSearchQuery();
    }

    public function getTableColumnSearchQueries(): array
    {
        return $this->getUserTableSettings('column_search') ?? parent::getTableColumnSearchQueries();
    }

    public function getTableColumnToggledHiddenState(): array
    {
        return $this->getUserTableSettings('column_visibility') ?? parent::getTableColumnToggledHiddenState();
    }

    // Fallback for other Filament versions
    public function getToggledHiddenColumns(): array
    {
        // Check if parent has this method to avoid error
        if (method_exists(parent::class, 'getToggledHiddenColumns')) {
            return $this->getUserTableSettings('column_visibility') ?? parent::getToggledHiddenColumns();
        }
        return $this->getUserTableSettings('column_visibility') ?? [];
    }

    // --- Save State ---

    public function updatedTableFilters(): void
    {
        parent::updatedTableFilters();
        $this->saveUserTableSettings('filters', $this->tableFilters);
    }

    public function updatedTableSortColumn(): void
    {
        parent::updatedTableSortColumn();
        $this->saveUserTableSettings('sort_column', $this->tableSortColumn);
    }

    public function updatedTableSortDirection(): void
    {
        parent::updatedTableSortDirection();
        $this->saveUserTableSettings('sort_direction', $this->tableSortDirection);
    }

    public function updatedTableSearchQuery(): void
    {
        parent::updatedTableSearchQuery();
        $this->saveUserTableSettings('search', $this->tableSearchQuery);
    }

    public function updatedTableColumnSearchQueries(): void
    {
        parent::updatedTableColumnSearchQueries();
        $this->saveUserTableSettings('column_search', $this->tableColumnSearchQueries);
    }

    public function updatedTableColumnToggledHiddenState(): void
    {
        \Illuminate\Support\Facades\Log::info('updatedTableColumnToggledHiddenState fired', ['state' => $this->tableColumnToggledHiddenState]);
        $this->saveUserTableSettings('column_visibility', $this->tableColumnToggledHiddenState);
    }

    public function updatedToggledHiddenColumns(): void
    {
        \Illuminate\Support\Facades\Log::info('updatedToggledHiddenColumns fired', ['state' => $this->toggledHiddenColumns]);
        $this->saveUserTableSettings('column_visibility', $this->toggledHiddenColumns ?? []);
    }

    public function updatedToggledTableColumns(): void
    {
        \Illuminate\Support\Facades\Log::info('updatedToggledTableColumns fired', ['state' => $this->toggledTableColumns]);
        $this->saveUserTableSettings('column_visibility', $this->toggledTableColumns ?? []);
    }

    // Catch-all for debugging or fallback
    public function updated($name, $value): void
    {
        \Illuminate\Support\Facades\Log::info("Updated property: {$name}", ['value' => $value]);

        if ($name === 'tableColumnToggledHiddenState' || 
            $name === 'toggledHiddenColumns' || 
            $name === 'toggledTableColumns' ||
            str_starts_with($name, 'toggledTableColumns.')) {
            
            // Determine which property holds the state
            $state = $this->toggledTableColumns 
                  ?? $this->toggledHiddenColumns 
                  ?? $this->tableColumnToggledHiddenState 
                  ?? [];

            $this->saveUserTableSettings('column_visibility', $state);
        }
        
        if ($name === 'tableFilters') {
            $this->saveUserTableSettings('filters', $value);
        }
    }

    protected function getUserTableSettings(string $key): mixed
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $tableId = $this->getTableIdentifier();
        $settings = $user->settings ?? [];
        
        $value = data_get($settings, "tables.{$tableId}.{$key}");
        \Illuminate\Support\Facades\Log::info("Loading setting: {$key} for table {$tableId}", ['value' => $value]);
        
        return $value;
    }

    protected function saveUserTableSettings(string $key, mixed $value): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $tableId = $this->getTableIdentifier();
        $settings = $user->settings ?? [];

        data_set($settings, "tables.{$tableId}.{$key}", $value);

        $user->settings = $settings;
        $user->save();

        \Illuminate\Support\Facades\Log::info("Saving setting: {$key} for table {$tableId}", ['value' => $value]);
    }

    protected function getTableIdentifier(): string
    {
        // Usamos el nombre de la clase como identificador único si no hay uno explícito
        return class_basename($this);
    }
}
