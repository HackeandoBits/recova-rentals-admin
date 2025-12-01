<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\QuickStats;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\UpcomingInterviews;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = -2;

    protected static string $view = 'filament.pages.dashboard';

    public function getHeading(): string
    {
        return 'Dashboard';
    }

    public function getSubheading(): ?string
    {
        return '¡Bienvenido! Aquí tienes un resumen de tu negocio de alquiler.';
    }

    public function getColumns(): int|array
    {
        // 4 columnas en XL para lograr el layout:
        // - StatsOverview ocupa todo el ancho
        // - Próximas Reservas (3 cols) + Estadísticas Rápidas (1 col)
        return [
            'default' => 1,
            'lg' => 2,
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

    // Acciones movidas al menú de usuario
}
