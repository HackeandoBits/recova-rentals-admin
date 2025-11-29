<?php

namespace App\Filament\Traits;

use Filament\Tables\Table;

trait PersistsTableConfig
{
    public function getPersistedFilterState(): array
    {
        return $this->getUserTableSettings('filters') ?? [];
    }

    public function getPersistedSortState(): array
    {
        return $this->getUserTableSettings('sort') ?? [];
    }

    public function getPersistedSearchState(): ?string
    {
        return $this->getUserTableSettings('search');
    }

    public function getPersistedColumnSearchState(): array
    {
        return $this->getUserTableSettings('column_search') ?? [];
    }

    public function getPersistedColumnVisibilityState(): array
    {
        return $this->getUserTableSettings('column_visibility') ?? [];
    }

    public function persistFilterState(array $filters): void
    {
        $this->saveUserTableSettings('filters', $filters);
    }

    public function persistSortState(array $sort): void
    {
        $this->saveUserTableSettings('sort', $sort);
    }

    public function persistSearchState(?string $search): void
    {
        $this->saveUserTableSettings('search', $search);
    }

    public function persistColumnSearchState(array $search): void
    {
        $this->saveUserTableSettings('column_search', $search);
    }

    public function persistColumnVisibilityState(array $visibility): void
    {
        $this->saveUserTableSettings('column_visibility', $visibility);
    }

    protected function getUserTableSettings(string $key): mixed
    {
        $user = auth()->user();
        if (! $user) {
            return null;
        }

        $tableId = $this->getTableIdentifier();
        $settings = $user->settings ?? [];

        // Estructura: settings['tables'][table_id][key]
        return data_get($settings, "tables.{$tableId}.{$key}");
    }

    protected function saveUserTableSettings(string $key, mixed $value): void
    {
        $user = auth()->user();
        if (! $user) {
            return;
        }

        $tableId = $this->getTableIdentifier();
        $settings = $user->settings ?? [];

        // Usamos data_set para facilitar la asignación anidada
        data_set($settings, "tables.{$tableId}.{$key}", $value);

        $user->settings = $settings;
        $user->save();
    }

    protected function getTableIdentifier(): string
    {
        // Usamos el nombre de la clase como identificador único si no hay uno explícito
        return class_basename($this);
    }
}
