<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class Calendar extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-calendar';

    protected static ?string $navigationLabel = 'Calendario';

    protected static ?string $navigationGroup = 'Agenda';

    protected static ?string $title = 'Calendario de Eventos';

    protected static ?int $navigationSort = 2;

    protected static string $view = 'filament.pages.calendar';

    public function mount(): void
    {
        /** @var \App\Services\GoogleCalendarService $service */
        $service = app(\App\Services\GoogleCalendarService::class);

        if (! $service->isConnected()) {
            \Filament\Notifications\Notification::make()
                ->title('Conexión requerida')
                ->body('Redirigiendo a Google para conectar el calendario...')
                ->warning()
                ->send();

            $this->redirect(route('google.redirect'));
        }
    }

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
            \Filament\Actions\CreateAction::make('create')
                ->label('Crear Reunión')
                ->model(\App\Models\Interview::class)
                ->form(\App\Filament\Resources\Interviews\Schemas\InterviewForm::schema())
                ->icon('heroicon-o-plus-circle')
                ->color('primary')
                ->modalHeading('Crear Reunión')
                ->modalSubmitActionLabel('Crear')
                ->modalWidth('4xl')
                ->visible(fn () => auth()->user()->can('create', \App\Models\Interview::class)),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [];
    }
}
