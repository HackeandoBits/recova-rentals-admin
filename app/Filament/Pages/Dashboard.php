<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\QuickStats;
use App\Filament\Widgets\StatsOverview;
use App\Filament\Widgets\UpcomingInterviews;
use Filament\Pages\Dashboard as BaseDashboard;
use Illuminate\Contracts\Support\Htmlable;

class Dashboard extends BaseDashboard
{
    protected static ?string $navigationLabel = 'Inicio';

    protected static ?string $navigationIcon = 'heroicon-o-home';

    protected static ?int $navigationSort = -2;

    public function getTitle(): string|Htmlable
    {
        return 'Inicio';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Inicio';
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

    protected function getHeaderActions(): array
    {
        $user = auth()->user();
        $hasToken = $user ? \App\Models\GoogleToken::where('user_id', $user->id)->exists() : false;

        return [
            \Filament\Actions\Action::make('connect_google')
                ->label('Conectar Google Calendar')
                ->icon('heroicon-o-link')
                ->color('primary')
                ->url(route('google.redirect'))
                ->visible(! $hasToken),

            \Filament\Actions\Action::make('disconnect_google')
                ->label('Desconectar Google')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->modalHeading('¿Desconectar Google Calendar?')
                ->modalDescription('Se dejarán de sincronizar los eventos. No se borrarán los eventos ya creados en Google.')
                ->url(route('google.disconnect'))
                ->visible($hasToken),
        ];
    }
}
