<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Dashboard as AdminDashboard;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\NavigationBuilder;
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
use Filament\View\PanelsRenderHook;

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
            ])
            ->userMenuItems([
                \Filament\Navigation\MenuItem::make()
                    ->label('Mi Perfil')
                    ->url(fn (): string => \App\Filament\Pages\Profile::getUrl())
                    ->icon('heroicon-o-user-circle'),
            ])
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $builder
                    ->items([
                        ...\App\Filament\Pages\Dashboard::getNavigationItems(),
                        ...\App\Filament\Pages\ReportsPage::getNavigationItems(), // Ahora como item fijo
                    ])
                    ->groups([
                        \Filament\Navigation\NavigationGroup::make('Agenda')
                            ->items([
                                ...\App\Filament\Pages\Calendar::getNavigationItems(),
                                ...\App\Filament\Resources\CalendarBlocks\CalendarBlockResource::getNavigationItems(),
                                ...\App\Filament\Resources\Interviews\InterviewResource::getNavigationItems(),
                            ]),
                    ]);
            })
            ->renderHook(
                'panels::head.end',
                fn (): string => <<<'HTML'
                    <style>
                        /* Forzar orden visual usando Flexbox */
                        .fi-topbar-nav > ul {
                            display: flex;
                            gap: 0.5rem; /* Espaciado consistente */
                        }

                        /* Por defecto todos tienen orden 0 */
                        .fi-topbar-item, .fi-topbar-group {
                            order: 0;
                        }

                        /* Mover Reportes al final (identificado por su enlace) */
                        .fi-topbar-item:has(a[href*="reports"]) {
                            order: 100 !important;
                        }
                        
                        /* Asegurar que Agenda (Grupo) esté antes que Reportes pero después de Dashboard */
                        /* Dashboard suele ser el primero por defecto */
                    </style>

                    <script>
                        // Usar delegación de eventos global para manejar actualizaciones de Livewire y asegurar detección
                        document.addEventListener('mouseover', (e) => {
                            // Verificar si estamos dentro de la navegación superior
                            const nav = e.target.closest('.fi-topbar-nav');
                            if (!nav) return;

                            // Verificar si estamos sobre un item
                            const item = e.target.closest('.fi-topbar-item');
                            if (!item) return;

                            // Buscar el botón disparador (que tenga aria-expanded)
                            const button = item.querySelector('button[aria-expanded]');
                            
                            // Si el botón existe y el menú está cerrado, simular click para abrir
                            if (button && button.getAttribute('aria-expanded') === 'false') {
                                button.click();
                            }
                        });
                    </script>
HTML,
            );
    }
}
