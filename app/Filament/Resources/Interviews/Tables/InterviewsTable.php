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

                TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'whatsapp' => 'WhatsApp',
                        'physical_meeting' => 'Reunión Física',
                        'virtual_meeting' => 'Reunión Virtual',
                        default => ucfirst($state),
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'whatsapp' => 'success',
                        'physical_meeting' => 'primary',
                        'virtual_meeting' => 'info',
                        default => 'gray',
                    })
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
                \Filament\Tables\Actions\ViewAction::make()
                    ->label('Ver Detalles')
                    ->iconButton() // Icono solo
                    ->infolist([
                        \Filament\Infolists\Components\Section::make('Información de la Reunión')
                            ->schema([
                                \Filament\Infolists\Components\TextEntry::make('title')
                                    ->label('Título'),
                                \Filament\Infolists\Components\TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->color(fn (string $state): string => match ($state) {
                                        'pending' => 'warning',
                                        'confirmed' => 'success',
                                        'cancelled' => 'danger',
                                        default => 'gray',
                                    }),
                                \Filament\Infolists\Components\TextEntry::make('start_at')
                                    ->label('Inicio')
                                    ->dateTime(),
                                \Filament\Infolists\Components\TextEntry::make('end_at')
                                    ->label('Fin')
                                    ->dateTime(),
                                \Filament\Infolists\Components\TextEntry::make('customer_name')
                                    ->label('Cliente'),
                                \Filament\Infolists\Components\TextEntry::make('customer_phone')
                                    ->label('Teléfono')
                                    ->url(fn ($record) => $record->customer_phone ? 'https://wa.me/'.preg_replace('/[^0-9]/', '', $record->customer_phone) : null, true)
                                    ->color('success'),
                                \Filament\Infolists\Components\TextEntry::make('customer_email')
                                    ->label('Email'),
                                \Filament\Infolists\Components\TextEntry::make('order_notes')
                                    ->label('Notas')
                                    ->columnSpanFull(),
                                \Filament\Infolists\Components\TextEntry::make('items_summary')
                                    ->label('Items Solicitados')
                                    ->state(fn ($record) => $record->items->map(fn ($item) => "• {$item->quantity}x {$item->name}")->join('<br>'))
                                    ->html()
                                    ->visible(fn ($record) => $record->items()->exists())
                                    ->columnSpanFull()
                                    ->color('gray'),
                            ])
                            ->columns(2),
                    ]),
                \Filament\Tables\Actions\Action::make('whatsapp')
                    ->label('WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn ($record) => $record->customer_phone
                        ? 'https://wa.me/'.preg_replace('/[^0-9]/', '', $record->customer_phone)
                        : null, shouldOpenInNewTab: true)
                    ->visible(fn ($record) => ! empty($record->customer_phone) && ! $record->trashed()),
                EditAction::make()
                    ->iconButton()
                    ->visible(fn ($record) => ! $record->trashed()),
                \Filament\Tables\Actions\DeleteAction::make()
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
            ->recordUrl(null)
            ->recordAction('view')
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
