<?php

namespace App\Providers\Filament;

use App\Filament\Pages\Auth\Login;
use App\Filament\Pages\Profile;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Navigation\MenuItem;
use Filament\Navigation\NavigationBuilder;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
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
            ->brandLogo(fn () => view('filament.components.recova-logo'))
            ->authGuard('web') // Usa el guard web de Laravel
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
            ->darkMode(true)
            ->renderHook(
                PanelsRenderHook::HEAD_END,
                fn () => view('filament.hooks.custom-assets'),
            )
            ->renderHook(
                PanelsRenderHook::HEAD_START,
                fn () => view('filament.hooks.login-styles'),
            )
            ->renderHook(
                'panels::auth.login.form.after',
                fn (): string => <<<'HTML'
                <div style="margin-top: 0.9rem; display: flex; flex-direction: column; gap: 0.4rem; align-items: stretch;">
                    <div style="position: relative; text-align: center; margin-block: 0.3rem;">
                        <div style="position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: rgba(148, 163, 184, 0.3);"></div>
                        <span style="position: relative; background: rgba(15, 23, 42, 0.96); padding-inline: 0.8rem; color: #9ca3af; font-size: 0.8rem;">O</span>
                    </div>
                    <a href="/auth/google/login"
                       style="display: inline-flex;
                              align-items: center;
                              justify-content: center;
                              gap: 0.6rem;
                              background: rgba(255, 255, 255, 0.08) !important;
                              color: #e5e7eb !important;
                              border: 1px solid rgba(148, 163, 184, 0.25) !important;
                              border-radius: 9999px !important;
                              padding: 0.6rem 1rem !important;
                              font-size: 0.875rem !important;
                              font-weight: 600 !important;
                              text-decoration: none !important;
                              transition: all 0.2s ease !important;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor" style="flex-shrink: 0;">
                            <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                            <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                            <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                            <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                        </svg>
                        Continua con Google
                    </a>
                </div>
                HTML
            )
            ->plugin(FilamentFullCalendarPlugin::make())

            // 👇 Ítem "Mi Perfil" en el menú de usuario (dropdown arriba a la derecha)
            ->userMenuItems([
                MenuItem::make()
                    ->label('Mi Perfil')
                    ->url(fn () => Profile::getUrl())
                    ->icon('heroicon-o-user-circle'),
            ])

            // Descubrimiento automático de resources, pages y widgets
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
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
            ->navigation(function (NavigationBuilder $builder): NavigationBuilder {
                return $builder
                    ->items([
                        ...\App\Filament\Pages\Dashboard::getNavigationItems(),
                    ])
                    ->groups([
                        \Filament\Navigation\NavigationGroup::make('Agenda')
                            ->items([
                                ...\App\Filament\Pages\Calendar::getNavigationItems(),
                                ...\App\Filament\Resources\CalendarBlocks\CalendarBlockResource::getNavigationItems(),
                                ...\App\Filament\Resources\Interviews\InterviewResource::getNavigationItems(),
                            ]),
                        \Filament\Navigation\NavigationGroup::make('') // Grupo vacío para forzar orden después de Agenda
                            ->items([
                                ...\App\Filament\Pages\ReportsPage::getNavigationItems(),
                            ]),
                    ]);
            });
    }
}
