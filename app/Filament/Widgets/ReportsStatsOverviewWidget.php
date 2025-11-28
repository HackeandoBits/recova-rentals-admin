<?php

namespace App\Filament\Widgets;

use App\Models\Interview;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Carbon;

class ReportsStatsOverviewWidget extends BaseWidget
{
    public ?array $dateRange = null;

    protected function getStats(): array
    {
        // Determinar el rango de fechas
        $from = $this->dateRange['from'] ?? Carbon::now()->startOfMonth();
        $to = $this->dateRange['to'] ?? Carbon::now()->endOfMonth();

        // Manejar si son strings
        if (is_string($from)) {
            $from = Carbon::parse($from);
        }
        if (is_string($to)) {
            $to = Carbon::parse($to);
        }

        // 1. Reservas del Mes
        $currentMonthBookings = Interview::whereBetween('created_at', [$from, $to])->count();

        // Mes anterior para comparación
        $previousFrom = $from->copy()->subMonth();
        $previousTo = $to->copy()->subMonth();
        $previousMonthBookings = Interview::whereBetween('created_at', [$previousFrom, $previousTo])->count();

        $trend = $previousMonthBookings > 0
            ? round((($currentMonthBookings - $previousMonthBookings) / $previousMonthBookings) * 100, 1)
            : 0;

        $trendDescription = $trend > 0
            ? "+{$trend}% vs mes anterior"
            : ($trend < 0 ? "{$trend}% vs mes anterior" : 'Sin cambios');

        $trendColor = $trend > 0 ? 'success' : ($trend < 0 ? 'danger' : 'gray');

        // 2. Próximas Reservas
        $upcomingBookings = Interview::where('status', 'confirmed')
            ->where('start_at', '>', Carbon::now())
            ->count();

        // 3. Tasa de Nuevos Clientes
        $newClientsThisMonth = Interview::whereBetween('created_at', [$from, $to])
            ->distinct('customer_email')
            ->count('customer_email');

        $returningClients = Interview::whereBetween('created_at', [$from, $to])
            ->whereIn('customer_email', function ($query) use ($from) {
                $query->select('customer_email')
                    ->from('interviews')
                    ->where('created_at', '<', $from)
                    ->whereNotNull('customer_email')
                    ->groupBy('customer_email');
            })
            ->distinct('customer_email')
            ->count('customer_email');

        $totalClients = $newClientsThisMonth + $returningClients;
        $newClientRate = $totalClients > 0
            ? round(($newClientsThisMonth / $totalClients) * 100, 1)
            : 0;

        return [
            Stat::make('Reservas del Período', $currentMonthBookings)
                ->description($trendDescription)
                ->descriptionIcon($trend > 0 ? 'heroicon-m-arrow-trending-up' : 'heroicon-m-arrow-trending-down')
                ->icon('heroicon-o-calendar-days')
                ->color($trendColor),

            Stat::make('Próximas Reservas', $upcomingBookings)
                ->description('Confirmadas y futuras')
                ->icon('heroicon-o-clock')
                ->color('primary'),

            Stat::make('Nuevos Clientes', $newClientRate.'%')
                ->description("{$newClientsThisMonth} nuevos de {$totalClients} totales")
                ->icon('heroicon-o-user-plus')
                ->color('info'),
        ];
    }
}
