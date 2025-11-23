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
        // TODO: reemplazar estos valores por consultas reales cuando tengas los modelos listos.
        return [
            'totalClients' => 6,
            'equipmentTypes' => 12,
            'successRate' => 94,
            'popularEquipment' => [
                ['name' => 'Pantallas LED', 'percentage' => 40],
                ['name' => 'Sistemas de Sonido', 'percentage' => 35],
                ['name' => 'Iluminación', 'percentage' => 25],
            ],
        ];
    }
}
