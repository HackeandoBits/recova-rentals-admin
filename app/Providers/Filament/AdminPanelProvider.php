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

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->login()
            ->authGuard('web') // Usa el guard web de Laravel
            ->colors([
                'primary' => Color::Amber,
            ])
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
            ->navigationGroups([
                'Agenda',
                'Reportes',
            ])
            ->renderHook(
                'panels::head.end',
                fn (): string => <<<'JS'
                    <script>
                        document.addEventListener('DOMContentLoaded', () => {
                            let currentOpenDropdown = null;

                            // Función para abrir un dropdown
                            function openDropdown(button) {
                                if (!button || button.getAttribute('aria-expanded') !== 'false') return;
                                button.click();
                                currentOpenDropdown = button;
                            }

                            // Función para cerrar un dropdown
                            function closeDropdown(button) {
                                if (!button || button.getAttribute('aria-expanded') !== 'true') return;
                                button.click();
                                if (currentOpenDropdown === button) {
                                    currentOpenDropdown = null;
                                }
                            }

                            // Event delegation para hover (abrir)
                            document.addEventListener('mouseover', (e) => {
                                const target = e.target.closest('.fi-topbar-item, [data-group-label]');
                                if (!target) return;

                                const trigger = target.querySelector('button[aria-expanded]');
                                if (!trigger) return;

                                // Si hay otro dropdown abierto, cerrarlo primero
                                if (currentOpenDropdown && currentOpenDropdown !== trigger) {
                                    closeDropdown(currentOpenDropdown);
                                }

                                openDropdown(trigger);
                            });

                            // Cerrar cuando el mouse sale del área del dropdown
                            document.addEventListener('mouseout', (e) => {
                                const target = e.target.closest('.fi-topbar-item, [data-group-label]');
                                if (!target || !currentOpenDropdown) return;

                                // Verificar si el mouse realmente salió del contenedor
                                const relatedTarget = e.relatedTarget;
                                if (relatedTarget && target.contains(relatedTarget)) {
                                    return; // El mouse sigue dentro del mismo dropdown
                                }

                                // Pequeño delay para evitar cierres accidentales
                                setTimeout(() => {
                                    // Verificar si el mouse está sobre algún dropdown o su contenido
                                    const hoveredElement = document.elementFromPoint(e.clientX, e.clientY);
                                    const isOverDropdown = hoveredElement && (
                                        hoveredElement.closest('.fi-topbar-item') ||
                                        hoveredElement.closest('[data-group-label]') ||
                                        hoveredElement.closest('[role="menu"]')
                                    );

                                    if (!isOverDropdown && currentOpenDropdown) {
                                        closeDropdown(currentOpenDropdown);
                                    }
                                }, 100);
                            });
                        });
                    </script>
JS,
            );
    }
}
