<?php

namespace App\Filament\Widgets;

use App\Models\Interview;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class LatestBookings extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 2;

    protected static ?string $heading = 'Últimos Pedidos';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                Interview::query()->latest()->limit(5)
            )
            ->columns([
                Tables\Columns\TextColumn::make('customer_name')
                    ->label('Cliente')
                    ->searchable(),
                Tables\Columns\TextColumn::make('event_date')
                    ->label('Fecha Evento')
                    ->date('d/m/Y')
                    ->sortable(),
                Tables\Columns\TextColumn::make('channel')
                    ->label('Canal')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'virtual_meeting' => 'Virtual',
                        'physical_meeting', 'office' => 'Presencial',
                        'whatsapp' => 'WhatsApp',
                        'email' => 'Email',
                        default => ucfirst($state),
                    })
                    ->colors([
                        'primary' => ['virtual_meeting', 'physical_meeting', 'office'],
                        'info' => 'email',
                        'success' => 'whatsapp',
                    ]),
                Tables\Columns\TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (string $state) => match ($state) {
                        'pending' => 'Pendiente',
                        'confirmed' => 'Confirmado',
                        'cancelled' => 'Cancelado',
                        default => ucfirst($state),
                    })
                    ->colors([
                        'warning' => 'pending',
                        'success' => 'confirmed',
                        'danger' => 'cancelled',
                    ]),
            ])
            ->paginated(false);
    }
}
