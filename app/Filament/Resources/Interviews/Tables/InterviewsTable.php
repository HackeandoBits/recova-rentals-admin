<?php

namespace App\Filament\Resources\Interviews\Tables;

use Filament\Tables\Actions\BulkActionGroup;
use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\SelectColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InterviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->label('Título')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->searchable()
                    ->toggleable(),

                TextColumn::make('start_at')
                    ->label('Inicio')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('end_at')
                    ->label('Fin')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(),
                SelectColumn::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'confirmed' => 'Confirmada',
                        'cancelled' => 'Cancelada',
                    ])
                    ->selectablePlaceholder(false)
                    ->width('150px')
                    ->toggleable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->actions([
                \Filament\Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn ($record) => $record->customer_phone
                        ? 'https://wa.me/'.preg_replace('/[^0-9]/', '', $record->customer_phone)
                        : null, shouldOpenInNewTab: true)
                    ->visible(fn ($record) => ! empty($record->customer_phone) && ! $record->trashed()),
                EditAction::make()
                    ->visible(fn ($record) => ! $record->trashed()),
                \Filament\Tables\Actions\DeleteAction::make()
                    ->visible(fn ($record) => ! $record->trashed()),
                \Filament\Tables\Actions\RestoreAction::make()
                    ->label('Restaurar')
                    ->visible(fn ($record) => $record->trashed()),
                \Filament\Tables\Actions\ForceDeleteAction::make()
                    ->label('Borrar Definitivamente')
                    ->visible(fn ($record) => $record->trashed()),
            ])
            ->filters([
                \Filament\Tables\Filters\TrashedFilter::make(),
            ])
            ->bulkActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    \Filament\Tables\Actions\RestoreBulkAction::make(),
                    \Filament\Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ]);
    }
}
