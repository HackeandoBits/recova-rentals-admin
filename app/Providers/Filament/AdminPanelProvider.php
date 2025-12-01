<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard as AdminDashboard;
use App\Filament\Pages\Profile;                    // 👈 IMPORTANTE
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;                  // 👈 IMPORTANTE
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
            ->login(Login::class)
            ->authGuard('web') // Usa el guard web de Laravel
            ->brandName('Recova Rentals Admin')
            ->brandLogo(fn() => view('filament.admin.logo'))
            ->topNavigation()
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
            ->viteTheme('resources/css/filament/admin/theme.css')
            ->darkMode(true)
            ->plugin(FilamentFullCalendarPlugin::make())

            // 👇 Ítem "Mi Perfil" en el menú de usuario (dropdown arriba a la derecha)
            ->userMenuItems([
                MenuItem::make()
                    ->label('Mi Perfil')
                    ->url(fn() => Profile::getUrl())
                    ->icon('heroicon-o-user-circle'),

                // Conectar / Desconectar Google
                MenuItem::make()
                    ->label(
                        fn() => auth()->user()?->googleToken()->exists()
                        ? 'Desconectar Google'
                        : 'Conectar Google'
                    )
                    ->url(
                        fn() => auth()->user()?->googleToken()->exists()
                        ? route('google.disconnect')
                        : route('google.redirect')
                    )
                    ->icon(
                        fn() => auth()->user()?->googleToken()->exists()
                        ? 'heroicon-o-x-circle'
                        : 'heroicon-o-link'
                    ),
            ])


            // Descubrimiento automático de resources, pages y widgets
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
            ->discoverResources(in: app_path('Filament/Resources/Interviews'), for: 'App\\Filament\\Resources\\Interviews')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
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
            ]);
    }
}
