<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class QuickStats extends Widget
{
    protected static ?int $sort = 3;

    protected static string $view = 'filament.widgets.quick-stats';

    protected int|string|array $columnSpan = [
        'sm' => 1,
        'lg' => 2,
        'xl' => 1,
    ];

    protected function getViewData(): array
    {
        // 1. Interviews este mes
        $interviewsThisMonth = \App\Models\Interview::whereBetween('created_at', [
            \Carbon\Carbon::now()->startOfMonth(),
            \Carbon\Carbon::now()->endOfMonth(),
        ])->count();

        // 2. Cancelaciones del mes
        $cancellationsThisMonth = \App\Models\Interview::where('status', 'cancelled')
            ->whereBetween('created_at', [
                \Carbon\Carbon::now()->startOfMonth(),
                \Carbon\Carbon::now()->endOfMonth(),
            ])->count();

        // 3. Día más popular (de la semana)
        $driver = \DB::connection()->getDriverName();

        $dayOfWeekSql = match ($driver) {
            'sqlsrv' => 'DATEPART(dw, start_at)',
            'sqlite' => 'strftime("%w", start_at) + 1', // SQLite 0=Domingo, Laravel espera 1=Domingo si se mapea igual
            'mysql' => 'DAYOFWEEK(start_at)',
            'pgsql' => 'EXTRACT(DOW FROM start_at) + 1',
            default => 'DAYOFWEEK(start_at)',
        };

        // SQL Server requiere agrupar por la expresión exacta, MySQL permite el alias
        $groupBySql = match ($driver) {
            'sqlsrv' => \DB::raw($dayOfWeekSql),
            default => 'day_of_week',
        };

        $popularDay = \App\Models\Interview::selectRaw("{$dayOfWeekSql} as day_of_week, COUNT(*) as count")
            ->whereNotNull('start_at')
            ->groupBy($groupBySql)
            ->orderByDesc('count')
            ->first();

        $daysOfWeek = [
            1 => 'Domingo',
            2 => 'Lunes',
            3 => 'Martes',
            4 => 'Miércoles',
            5 => 'Jueves',
            6 => 'Viernes',
            7 => 'Sábado',
        ];

        $mostPopularDay = $popularDay ? $daysOfWeek[$popularDay->day_of_week] : 'Sin datos';

        // 4. Top 5 Productos/Combos más populares
        $popularProducts = \DB::table('interview_items')
            ->select('name', 'product_type', \DB::raw('SUM(quantity) as total_quantity'))
            ->groupBy('name', 'product_type')
            ->orderByDesc('total_quantity')
            ->limit(5)
            ->get();

        $totalItems = $popularProducts->sum('total_quantity');

        $topProducts = $popularProducts->map(function ($product) use ($totalItems) {
            return [
                'name' => $product->name,
                'type' => $product->product_type === 'combo' ? 'Combo' : 'Item',
                'percentage' => $totalItems > 0 ? round(($product->total_quantity / $totalItems) * 100) : 0,
            ];
        })->toArray();

        return [
            'interviewsThisMonth' => $interviewsThisMonth,
            'cancellationsThisMonth' => $cancellationsThisMonth,
            'mostPopularDay' => $mostPopularDay,
            'topProducts' => $topProducts ?: [
                ['name' => 'Sin datos aún', 'type' => '-', 'percentage' => 100],
            ],
        ];
    }
}
