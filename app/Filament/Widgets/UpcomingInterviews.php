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
        'sm' => 1,
        'lg' => 2,
        'xl' => 3,
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

            Tables\Columns\TextColumn::make('title')
                ->label('Título')
                ->limit(40)
                ->searchable(),

            Tables\Columns\TextColumn::make('status')
                ->label('Estado')
                ->badge()
                ->formatStateUsing(fn (string $state) => match ($state) {
                    'pending' => 'Pendiente',
                    'confirmed' => 'Confirmada',
                    'cancelled' => 'Cancelada',
                    default => ucfirst($state),
                })
                ->colors([
                    'warning' => 'pending',
                    'success' => 'confirmed',
                    'danger' => 'cancelled',
                ]),
        ];
    }
}
