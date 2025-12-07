<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\CalendarWidget;
use Filament\Pages\Page;

class Calendar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Calendario';

    protected static ?string $navigationGroup = 'Agenda';

    protected static ?string $title = 'Calendario de Eventos';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.calendar';

    public function getSubheading(): ?string
    {
        return 'Gestiona tus eventos y recordatorios.';
    }

    public static function getNavigationBadge(): ?string
    {
        // Mostrar el número de eventos de hoy
        $today = \Carbon\Carbon::today();
        $todayEvents = \App\Models\Interview::whereDate('start_at', $today)
            ->where('status', '!=', 'cancelled')
            ->count();

        return $todayEvents > 0 ? (string) $todayEvents : null;
    }

    public static function getNavigationBadgeColor(): string|array|null
    {
        return 'primary';
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\Action::make('create')
                ->label('Crear Reunión')
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->action(function () {
                    // Usar getUrl() es más seguro que hardcodear la ruta
                    return redirect()->to(\App\Filament\Resources\Interviews\InterviewResource::getUrl('create'));
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
