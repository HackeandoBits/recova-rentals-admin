<?php

namespace App\Filament\Widgets;

use App\Models\Interview;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Reactive;

class BookingStatusChartWidget extends ChartWidget
{
    protected static ?string $heading = 'Distribución de Estados de Reservas';

    protected static ?int $sort = 3;

    // protected int|string|array $columnSpan = 'full'; // Eliminado para permitir grid

    protected static ?string $maxHeight = '300px';

    protected static ?string $pollingInterval = null;

    protected $listeners = ['updateChartData' => '$refresh'];

    #[Reactive]
    public ?array $dateRange = null;

    public function updateChartData(): void
    {
        // This method is called by Filament's polling mechanism
        // The chart will auto-update when dateRange changes
    }

    protected function getData(): array
    {
        // Determinar el rango de fechas
        $from = $this->dateRange['from'] ?? Carbon::now()->startOfMonth();
        $to = $this->dateRange['to'] ?? Carbon::now()->endOfMonth();

        if (is_string($from)) {
            $from = Carbon::parse($from);
        }
        if (is_string($to)) {
            $to = Carbon::parse($to);
        }

        // Obtener conteos por estado
        $statusCounts = Interview::whereBetween('created_at', [$from, $to])
            ->selectRaw('status, COUNT(*) as count')
            ->groupBy('status')
            ->pluck('count', 'status');

        $pending = $statusCounts['pending'] ?? 0;
        $confirmed = $statusCounts['confirmed'] ?? 0;
        $cancelled = $statusCounts['cancelled'] ?? 0;
        $completed = $statusCounts['completed'] ?? 0;

        return [
            'datasets' => [
                [
                    'label' => 'Reservas',
                    'data' => [$pending, $confirmed, $cancelled, $completed],
                    'backgroundColor' => [
                        'rgb(251, 191, 36)', // Amarillo - Pending
                        'rgb(34, 197, 94)',  // Verde - Confirmed
                        'rgb(239, 68, 68)',  // Rojo - Cancelled
                        'rgb(59, 130, 246)', // Azul - Completed
                    ],
                ],
            ],
            'labels' => ['Pendiente', 'Confirmada', 'Cancelada', 'Completada'],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'plugins' => [
                'legend' => [
                    'display' => true,
                    'position' => 'bottom',
                ],
            ],
        ];
    }
}
