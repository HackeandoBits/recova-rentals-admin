<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Dashboard as AdminDashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Saade\FilamentFullCalendar\FilamentFullCalendarPlugin;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->brandName('Recova Rentals Admin')
            ->topNavigation()
            ->authGuard('web') // Usa el guard web de Laravel
            ->colors([
                'primary' => [
                    50 => '#fdf2fb',
                    100 => '#fbe4f7',
                    200 => '#f8c9ee',
                    300 => '#f39ee0',
                    400 => '#ec66ce',
                    500 => '#e64ccc', // Client Accent
                    600 => '#c92aab',
                    700 => '#a9208b',
                    800 => '#8b1d71',
                    900 => '#741d5d',
                    950 => '#361636', // Client Primary (Dark Background)
                ],
                'gray' => Color::Zinc,
            ])
            ->darkMode(true) // Forzar o asegurar modo oscuro por defecto si es posible, o dejar que el usuario lo elija pero con paleta oscura bien definida
            ->plugin(FilamentFullCalendarPlugin::make())
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverResources(in: app_path('Filament/Resources/Interviews'), for: 'App\\Filament\\Resources\\Interviews')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
            ->pages([
                AdminDashboard::class,
            ])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([
                Authenticate::class,
            ])
            ->userMenuItems([
                \Filament\Navigation\MenuItem::make()
                    ->label('Mi Perfil')
                    ->url(fn (): string => \App\Filament\Pages\Profile::getUrl())
                    ->icon('heroicon-o-user-circle'),
            ])
            ->navigationGroups([
                'Agenda',
            ]);
    }
}
