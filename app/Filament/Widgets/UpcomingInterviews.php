<?php

namespace App\Filament\Widgets;

use App\Models\Interview;
use Filament\Tables;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class UpcomingInterviews extends BaseWidget
{
    protected static ?int $sort = 2;

    protected static ?string $heading = 'Próximas Reservas';

    protected int|string|array $columnSpan = [
        'default' => 1,   // ocupa toda la fila cuando solo hay 1 col (mobile)
        'lg' => 1,
    ];

    protected function getTableQuery(): Builder
    {
        $now = Carbon::now();

        return Interview::query()
            ->where('start_at', '>=', $now)
            ->where('status', '!=', 'cancelled')
            ->orderBy('start_at')
            ->limit(20);
    }

    protected function getTableColumns(): array
    {
        return [
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
                    if (!$state) {
                        return '—';
                    }

                    // Formatear teléfono argentino
                    if (strlen($state) >= 10) {
                        return '+54 ' . substr($state, 0, 3) . ' ' . substr($state, 3);
                    }

                    return $state;
                })
                ->copyable()
                ->copyMessage('Teléfono copiado')
                ->icon('heroicon-m-phone'),

            Tables\Columns\TextColumn::make('status')
                ->label('Estado')
                ->badge()
                ->formatStateUsing(fn(string $state) => match ($state) {
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
        ];
    }
}
