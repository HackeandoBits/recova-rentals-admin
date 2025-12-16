<?php

namespace App\Filament\Resources\Interviews\Tables;

use Filament\Tables\Actions\DeleteBulkAction;
use Filament\Tables\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InterviewsTable
{
    // use \App\Filament\Traits\PersistsTableConfig; // Moved to ListInterviews page

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

                \Filament\Tables\Columns\SelectColumn::make('channel')
                    ->label('Canal')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'physical_meeting' => 'Reunión Física',
                        'virtual_meeting' => 'Reunión Virtual',
                        'google_calendar' => 'Google Calendar',
                    ])
                    ->disabled(fn () => ! auth()->user()?->isAdmin())
                    ->selectablePlaceholder(false)
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
                \Filament\Tables\Columns\SelectColumn::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'confirmed' => 'Confirmada',
                        'cancelled' => 'Cancelada',
                        'completed' => 'Completada',
                    ])
                    ->disabled(fn () => ! auth()->user()?->isAdmin())
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
                    ->modalWidth('md')
                    ->infolist([
                        \Filament\Infolists\Components\Grid::make(2)
                            ->schema([
                                \Filament\Infolists\Components\Group::make([
                                    \Filament\Infolists\Components\TextEntry::make('title')
                                        ->label('Título'),
                                    \Filament\Infolists\Components\TextEntry::make('start_at')
                                        ->label('Inicio')
                                        ->dateTime('d/m/Y H:i'),
                                    \Filament\Infolists\Components\TextEntry::make('end_at')
                                        ->label('Fin')
                                        ->dateTime('d/m/Y H:i'),
                                    \Filament\Infolists\Components\TextEntry::make('customer_name')
                                        ->label('Cliente'),
                                    \Filament\Infolists\Components\TextEntry::make('customer_email')
                                        ->label('Email'),
                                ]),
                                \Filament\Infolists\Components\Group::make([
                                    \Filament\Infolists\Components\TextEntry::make('status')
                                        ->label('Estado')
                                        ->badge()
                                        ->formatStateUsing(fn (string $state) => match ($state) {
                                            'pending' => 'Pendiente',
                                            'confirmed' => 'Confirmada',
                                            'cancelled' => 'Cancelada',
                                            'completed' => 'Completada',
                                            default => ucfirst($state),
                                        })
                                        ->color(fn (string $state): string => match ($state) {
                                            'pending' => 'warning',
                                            'confirmed' => 'success',
                                            'cancelled' => 'danger',
                                            'completed' => 'info',
                                            default => 'gray',
                                        }),
                                    \Filament\Infolists\Components\TextEntry::make('channel')
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
                                        }),
                                    \Filament\Infolists\Components\TextEntry::make('customer_phone')
                                        ->label('Teléfono')
                                        ->url(fn ($record) => $record->customer_phone ? 'https://wa.me/'.preg_replace('/[^0-9]/', '', $record->customer_phone) : null, true)
                                        ->color('success')
                                        ->icon('heroicon-m-phone'),
                                    \Filament\Infolists\Components\TextEntry::make('order_notes')
                                        ->label('Notas')
                                        ->placeholder('Sin notas'),
                                    \Filament\Infolists\Components\TextEntry::make('items_summary')
                                        ->label('Productos Solicitados')
                                        ->state(fn ($record) => $record->items->map(fn ($item) => "• {$item->quantity}x {$item->name}")->join('<br>'))
                                        ->html()
                                        ->visible(fn ($record) => $record->items()->exists())
                                        ->color('gray'),
                                ]),
                            ]),
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
                    ->modalHeading('Editar Reunión')
                    ->modalWidth('4xl')
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
                \Filament\Tables\Filters\SelectFilter::make('channel')
                    ->label('Canal')
                    ->options([
                        'whatsapp' => 'WhatsApp',
                        'physical_meeting' => 'Reunión Física',
                        'virtual_meeting' => 'Reunión Virtual',
                        'google_calendar' => 'Google Calendar',
                    ])
                    ->placeholder('Todos los canales'),

                \Filament\Tables\Filters\SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'pending' => 'Pendiente',
                        'confirmed' => 'Confirmada',
                        'cancelled' => 'Cancelada',
                    ])
                    ->placeholder('Todos los estados'),

                \Filament\Tables\Filters\TrashedFilter::make(),
            ])
            ->bulkActions([
                DeleteBulkAction::make()
                    ->label('Eliminar seleccionados'),
                \Filament\Tables\Actions\RestoreBulkAction::make()
                    ->label('Restaurar seleccionados'),
                \Filament\Tables\Actions\ForceDeleteBulkAction::make()
                    ->label('Borrar definitivamente'),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
