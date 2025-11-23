<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\QuickStats;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\UpcomingInterviews;
use BackedEnum;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Dashboard';

    public static BackedEnum|string|null $navigationIcon = 'heroicon-o-home';

    public function getTitle(): string|Htmlable
    {
        return 'Panel de Control';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Panel de Control';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return '¡Bienvenido! Aquí tienes un resumen de tu negocio de alquiler.';
    }

    public function getColumns(): int|array
    {
        // 4 columnas en XL para lograr el layout:
        // - StatsOverview ocupa todo el ancho
        // - Próximas Reservas (3 cols) + Estadísticas Rápidas (1 col)
        return [
            'sm' => 1,
            'lg' => 2,
            'xl' => 4,
        ];
    }

    public function getWidgets(): array
    {
        return [
            StatsOverview::class,
            UpcomingInterviews::class,
            QuickStats::class,
        ];
    }
}
