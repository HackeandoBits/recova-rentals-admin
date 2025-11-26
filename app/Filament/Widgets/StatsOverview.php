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
        $pedidosPendientes = \App\Models\Booking::where('status', 'pending')->count();

        $reunionesHoy = Interview::whereDate('start_at', Carbon::today())
            ->where('status', '!=', 'cancelled')
            ->count();

        $pedidosMes = \App\Models\Booking::whereBetween('created_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])->count();

        return [
            Stat::make('Pedidos Pendientes', $pedidosPendientes)
                ->description('Requieren atención')
                ->icon('heroicon-o-inbox-stack')
                ->color($pedidosPendientes > 0 ? 'danger' : 'success'),

            Stat::make('Reuniones Hoy', $reunionesHoy)
                ->description('Agenda del día')
                ->icon('heroicon-o-calendar')
                ->color('primary'),

            Stat::make('Nuevos Pedidos (Mes)', $pedidosMes)
                ->description('Total este mes')
                ->icon('heroicon-o-chart-bar')
                ->color('info'),
        ];
    }
}
