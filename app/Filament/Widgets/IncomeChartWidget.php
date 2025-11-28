<?php

namespace App\Filament\Widgets;

use App\Models\Interview;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class IncomeChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Tendencia de Ingresos (Últimos 30 Días)';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    public ?array $dateRange = null;

    protected function getData(): array
    {
        // Determinar el rango de fechas
        $endDate = $this->dateRange['to'] ?? Carbon::now();
        $startDate = $this->dateRange['from'] ?? Carbon::now()->subDays(29);

        if (is_string($endDate)) {
            $endDate = Carbon::parse($endDate);
        }
        if (is_string($startDate)) {
            $startDate = Carbon::parse($startDate);
        }

        $days = [];
        $data = [];

        // Generar array de días
        $currentDate = $startDate->copy();
        while ($currentDate <= $endDate) {
            $dayLabel = $currentDate->format('d/m');
            $days[] = $dayLabel;

            // Contar reservas confirmadas de ese día
            $count = Interview::whereDate('start_at', $currentDate->toDateString())
                ->where('status', 'confirmed')
                ->count();

            // Simular ingresos (5000 por reserva)
            $data[] = $count * 5000;

            $currentDate->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Ingresos (ARS)',
                    'data' => $data,
                    'borderColor' => 'rgb(59, 130, 246)',
                    'backgroundColor' => 'rgba(59, 130, 246, 0.1)',
                    'fill' => true,
                    'tension' => 0.4,
                ],
            ],
            'labels' => $days,
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                ],
            ],
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'callback' => 'function(value) { return "$" + value.toLocaleString(); }',
                    ],
                ],
            ],
        ];
    }
}
