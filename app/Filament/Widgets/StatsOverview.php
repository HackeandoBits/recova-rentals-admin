<?php

namespace App\Filament\Widgets;

use App\Models\Interview;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class StatsOverview extends BaseWidget
{
    protected static ?int $sort = 1;

    protected int|string|array $columnSpan = 'full';

    protected function getStats(): array
    {
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth = Carbon::now()->endOfMonth();

        $baseQuery = Interview::query()
            ->whereBetween('start_at', [$startOfMonth, $endOfMonth]);

        $totalReservas = (clone $baseQuery)
            ->where('status', '!=', 'cancelled')
            ->count();

        $confirmadas = (clone $baseQuery)
            ->where('status', 'confirmed')
            ->count();

        $pendientes = (clone $baseQuery)
            ->where('status', 'pending')
            ->count();

        // TODO: reemplazar por cálculo real de ingresos (Bookings / montos)
        $ingresosEstimados = 12450;

        return [
            Stat::make('Total Reservas', $totalReservas)
                ->description('Este mes')
                ->icon('heroicon-o-calendar-days')
                ->color('primary'),

            Stat::make('Confirmadas', $confirmadas)
                ->description('Listas para ejecutar')
                ->icon('heroicon-o-check-circle')
                ->color('success'),

            Stat::make('Pendientes', $pendientes)
                ->description('Esperando confirmación')
                ->icon('heroicon-o-exclamation-circle')
                ->color('warning'),

            Stat::make('Ingresos', '$' . number_format($ingresosEstimados, 0, ',', '.'))
                ->description('Estimado mensual')
                ->icon('heroicon-o-currency-dollar')
                ->color('pink'),
        ];
    }
}
