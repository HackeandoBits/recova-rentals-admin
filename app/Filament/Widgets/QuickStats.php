<?php

namespace App\Filament\Widgets;

use Filament\Widgets\Widget;

class QuickStats extends Widget
{
    protected static ?int $sort = 3;

    protected string $view = 'filament.widgets.quick-stats';

    protected int|string|array $columnSpan = [
        'sm' => 1,
        'lg' => 2,
        'xl' => 1,
    ];

    protected function getViewData(): array
    {
        $totalClients = \App\Models\Interview::whereNotNull('customer_email')
            ->distinct('customer_email')
            ->count('customer_email');

        $confirmedInterviews = \App\Models\Interview::where('status', 'confirmed')->count();
        $totalInterviews = \App\Models\Interview::count();
        $successRate = $totalInterviews > 0 ? round(($confirmedInterviews / $totalInterviews) * 100) : 0;

        // Equipment types based on service_type
        $equipmentTypes = \App\Models\Interview::whereNotNull('service_type')
            ->distinct('service_type')
            ->count('service_type');

        // Most popular services
        $popularServices = \App\Models\Interview::selectRaw('service_type, COUNT(*) as count')
            ->whereNotNull('service_type')
            ->groupBy('service_type')
            ->orderByDesc('count')
            ->limit(3)
            ->get();

        $totalServices = $popularServices->sum('count');

        $popularEquipment = $popularServices->map(function ($service) use ($totalServices) {
            return [
                'name' => $service->service_type ?? 'Sin especificar',
                'percentage' => $totalServices > 0 ? round(($service->count / $totalServices) * 100) : 0,
            ];
        })->toArray();

        return [
            'totalClients' => $totalClients,
            'equipmentTypes' => $equipmentTypes ?: 1,
            'successRate' => $successRate,
            'popularEquipment' => $popularEquipment ?: [
                ['name' => 'Sin datos aún', 'percentage' => 100],
            ],
        ];
    }

}
