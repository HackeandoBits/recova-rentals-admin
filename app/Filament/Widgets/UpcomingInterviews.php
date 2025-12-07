<?php

namespace App\Filament\Widgets;

use App\Models\Interview;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Support\Carbon;

class UpcomingInterviews extends BaseWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = [
        'sm' => 1,
        'lg' => 2,
        'xl' => 3,
    ];

    public function table(Table $table): Table
    {
        $now = Carbon::now();

        return $table
            ->heading('Próximas Reservas')
            ->query(
                Interview::query()
                    ->where('start_at', '>=', $now)
                    ->where('status', '!=', 'cancelled')
                    ->orderBy('start_at')
                    ->limit(20)
            )
            ->columns([
                Tables\Columns\TextColumn::make('start_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->searchable()
                    ->limit(25),

                Tables\Columns\TextColumn::make('customer_phone')
                    ->label('Teléfono')
                    ->formatStateUsing(function ($state) {
                        if (! $state) {
                            return '—';
                        }

                        // Formatear teléfono argentino
                        if (strlen($state) >= 10) {
                            return '+54 '.substr($state, 0, 3).' '.substr($state, 3);
                        }

                        return $state;
                    })
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->icon('heroicon-m-phone'),

                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'Pendiente',
                        'confirmed' => 'Confirmada',
                        'cancelled' => 'Cancelada',
                        'completed' => 'Completada',
                        default => ucfirst($state),
                    })
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'danger' => 'cancelled',
                        'info' => 'completed',
                    ]),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->iconButton()
                    ->size('lg')
                    ->modalWidth('md')
                    ->modalHeading('Detalles de la Reunión')
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
            ])
            ->recordAction('view');
    }
}
