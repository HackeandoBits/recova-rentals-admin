<?php

namespace App\Filament\Resources\CalendarBlocks\Tables;

use Filament\Tables\Actions\DeleteAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class CalendarBlocksTable
{
    // use \App\Filament\Traits\PersistsTableConfig; // Moved to ListCalendarBlocks page

    public static function configure(Table $table): Table
    {
        return $table
            ->poll('5s')
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('kind')
                    ->badge()
                    ->label('Tipo')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'manual' => 'Manual',
                        'mantenimiento' => 'Mantenimiento',
                        'feriado' => 'Feriado',
                        'otro' => 'Otro',
                        default => $state,
                    })
                    ->colors([
                        'primary' => 'manual',
                        'warning' => 'mantenimiento',
                        'success' => 'feriado',
                        'secondary' => 'otro',
                    ])
                    ->toggleable(),

                IconColumn::make('is_all_day')
                    ->boolean()
                    ->label('Día completo')
                    ->toggleable(),

                TextColumn::make('starts_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Desde')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('ends_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Hasta')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('sync_status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending' => 'Pendiente',
                        'synced' => 'Sincronizado',
                        'failed' => 'Falló',
                        default => $state,
                    })
                    ->colors([
                        'gray' => 'pending',
                        'success' => 'synced',
                        'danger' => 'failed',
                    ])
                    ->toggleable(),

                TextColumn::make('synced_at')
                    ->dateTime('d/m/Y H:i')
                    ->label('Sincronizado')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                \Filament\Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                // \Filament\Tables\Actions\Action::make('retry_sync')
                //     ->label('Sincronizar')
                //     ->icon('heroicon-o-arrow-path')
                //     ->color('warning')
                //     ->action(function ($record) {
                //         \App\Jobs\SyncSingleBlockJob::dispatchSync($record->id);
                //         \Filament\Notifications\Notification::make()
                //             ->title('Sincronizado')
                //             ->success()
                //             ->send();
                //     }),
                EditAction::make()
                    ->iconButton()
                    ->visible(fn ($record) => ! $record->trashed()),
                DeleteAction::make()
                    ->iconButton()
                    ->visible(fn ($record) => ! $record->trashed()),
                \Filament\Tables\Actions\RestoreAction::make()
                    ->label('Restaurar')
                    ->iconButton()
                    ->visible(fn ($record) => $record->trashed()),
                \Filament\Tables\Actions\ForceDeleteAction::make()
                    ->label('Borrar Definitivamente')
                    ->iconButton()
                    ->visible(fn ($record) => $record->trashed()),
            ])
            ->bulkActions([
                \Filament\Tables\Actions\DeleteBulkAction::make()
                    ->label('Eliminar seleccionados'),
                \Filament\Tables\Actions\RestoreBulkAction::make()
                    ->label('Restaurar seleccionados'),
                \Filament\Tables\Actions\ForceDeleteBulkAction::make()
                    ->label('Borrar definitivamente'),
            ])
            ->persistFiltersInSession()
            ->persistSortInSession()
            ->persistSearchInSession()
            ->persistColumnSearchesInSession()
            ->defaultSort('created_at', 'desc');
    }
}
