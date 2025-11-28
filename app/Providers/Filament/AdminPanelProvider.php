<?php

namespace App\Providers\Filament;

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
use Filament\Navigation\NavigationBuilder;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;

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
                        document.addEventListener('DOMContentLoaded', () => {
                            // Lógica para abrir dropdowns al pasar el mouse (Hover)
                            const nav = document.querySelector('.fi-topbar-nav');
                            if (!nav) return;

                            nav.addEventListener('mouseover', (e) => {
                                const group = e.target.closest('[data-group-label]');
                                if (!group) return;

                                const button = group.querySelector('button[aria-expanded="false"]');
                                if (button) {
                                    button.click();
                                }
                            });

                            // Cerrar al salir del grupo
                            nav.addEventListener('mouseout', (e) => {
                                const group = e.target.closest('[data-group-label]');
                                if (!group) return;

                                // Verificar si el mouse realmente salió del grupo y sus hijos
                                if (group.contains(e.relatedTarget)) return;

                                const button = group.querySelector('button[aria-expanded="true"]');
                                if (button) {
                                    button.click();
                                }
                            });
                        });
                    </script>
HTML,
            );
    }
}
