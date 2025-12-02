<?php

namespace App\Filament\Widgets;

use App\Models\Interview;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class BookingTrendChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Tendencia de Solicitudes';

    protected static ?int $sort = 2;

    // protected int|string|array $columnSpan = 'full'; // Eliminado para permitir grid

    protected static ?string $maxHeight = '300px';

    public ?array $dateRange = null;

    public function updateChartData(): void
    {
        // This method is called by Filament's polling mechanism
        // The chart will auto-update when dateRange changes
    }

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

            // Contar solicitudes creadas ese día
            $count = Interview::whereDate('created_at', $currentDate->toDateString())
                ->count();

            $data[] = $count;

            $currentDate->addDay();
        }

        return [
            'datasets' => [
                [
                    'label' => 'Solicitudes',
                    'data' => $data,
                    'borderColor' => 'rgb(139, 92, 246)', // Morado
                    'backgroundColor' => 'rgba(139, 92, 246, 0.1)',
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
                        'stepSize' => 1,
                    ],
                ],
            ],
        ];
    }
}
