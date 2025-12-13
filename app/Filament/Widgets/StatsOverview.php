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
        $pedidosPendientes = Interview::where('status', 'pending')->count();

        $reunionesHoy = Interview::whereDate('start_at', Carbon::today())
            ->where('status', '!=', 'cancelled')
            ->count();

        $pedidosMes = Interview::whereBetween('created_at', [
            Carbon::now()->startOfMonth(),
            Carbon::now()->endOfMonth(),
        ])->count();

        $totalBookings = Interview::count();
        $confirmedBookings = Interview::where('status', 'confirmed')->count();
        $successRate = $totalBookings > 0 ? round(($confirmedBookings / $totalBookings) * 100) : 0;

        $totalClients = Interview::whereNotNull('customer_email')
            ->where('customer_email', '!=', '')
            ->distinct('customer_email')
            ->count('customer_email');

        return [
            Stat::make('Pedidos Pendientes', $pedidosPendientes)
                ->description('Requieren atención')
                ->icon('heroicon-o-inbox-stack')
                ->color($pedidosPendientes > 0 ? 'danger' : 'success')
                ->url(route('filament.admin.resources.interviews.interviews.index'))
                ->extraAttributes(['class' => 'rr-stat-card']),

            Stat::make('Reuniones Hoy', $reunionesHoy)
                ->description('Agenda del día')
                ->icon('heroicon-o-calendar')
                ->color('primary')
                ->url(route('filament.admin.pages.calendar'))
                ->extraAttributes(['class' => 'rr-stat-card cursor-pointer']),

            Stat::make('Tasa de Éxito', $successRate . '%')
                ->description('Reservas confirmadas')
                ->icon('heroicon-o-chart-pie')
                ->color('success')
                ->extraAttributes(['class' => 'rr-stat-card']),

            Stat::make('Total Clientes', $totalClients)
                ->description('Únicos registrados')
                ->icon('heroicon-o-users')
                ->color('info')
                ->extraAttributes(['class' => 'rr-stat-card']),
        ];
    }
}
