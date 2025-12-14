<?php

namespace App\Filament\Traits;

trait PersistsTableConfig
{
    // --- Load State ---

    // Force load on mount
    public function mountPersistsTableConfig(): void
    {
        $settings = $this->getUserTableSettings('column_visibility');
        if ($settings) {
            // Attempt to force the state into the Livewire property
            // We try multiple known property names for V3/V2 compatibility
            if (property_exists($this, 'tableColumnToggledHiddenState')) {
                $this->tableColumnToggledHiddenState = $settings;
            }
            if (property_exists($this, 'toggledHiddenColumns')) {
                $this->toggledHiddenColumns = $settings;
            }
            if (property_exists($this, 'toggledTableColumns')) {
                $this->toggledTableColumns = $settings;
            }

            \Illuminate\Support\Facades\Log::info('TRAIT: Forced column visibility settings on mount', ['settings' => $settings]);
        }
    }

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

    // V3 Candidate for column visibility
    public function getTableColumnVisibilityState(): array
    {
        return $this->getUserTableSettings('column_visibility') ?? parent::getTableColumnVisibilityState();
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
        $this->saveUserTableSettings('column_visibility', $this->tableColumnToggledHiddenState);
    }

    public function updatedToggledHiddenColumns(): void
    {
        $this->saveUserTableSettings('column_visibility', $this->toggledHiddenColumns ?? []);
    }

    public function updatedToggledTableColumns(): void
    {
        $this->saveUserTableSettings('column_visibility', $this->toggledTableColumns ?? []);
    }

    // Catch-all for debugging or fallback - Optimized to avoid double logging
    public function updated($name, $value): void
    {
        // Remove specific column visibility checks here because they are handled by
        // updatedTableColumnToggledHiddenState, updatedToggledHiddenColumns, etc.
        // We only keep the start_with check for nested properties if needed.

        if (str_starts_with($name, 'toggledTableColumns.')) {
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

    protected ?array $cachedUserTableSettings = null;

    protected function getUserTableSettings(string $key): mixed
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $tableId = $this->getTableIdentifier();

        // Use cached settings if available to avoid DB/JSON spam
        if ($this->cachedUserTableSettings === null) {
            // Reload fresh to be sure, but only once per request
            $freshUser = $user->fresh();
            $this->cachedUserTableSettings = $freshUser->settings ?? [];
        }

        return data_get($this->cachedUserTableSettings, "tables.{$tableId}.{$key}");
    }

    protected function saveUserTableSettings(string $key, mixed $value): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $tableId = $this->getTableIdentifier();

        // Ensure we work with latest data
        if ($this->cachedUserTableSettings === null) {
            $this->cachedUserTableSettings = $user->settings ?? [];
        }

        // Update local cache
        data_set($this->cachedUserTableSettings, "tables.{$tableId}.{$key}", $value);

        // Save using Eloquent but quietly (no events, no updated_at timestamp update if unnecessary)
        // saveQuietly() requires the model to exist.
        // We use fresh instance to avoid race conditions with other livewire updates if possible,
        // but for now updating the AUTH user instance is the standard way.

        $user->settings = $this->cachedUserTableSettings;

        // Use persistence without events for performance
        $user->saveQuietly();
    }

    protected function getTableIdentifier(): string
    {
        // Usamos el nombre de la clase como identificador único si no hay uno explícito
        return class_basename($this);
    }
}
