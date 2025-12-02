<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;

class Login extends BaseLogin
{
    /**
     * Ocultamos el heading/subheading que Filament muestra
     * arriba del formulario (fuera de la card).
     */
    public function getHeading(): string|Htmlable
    {
        return '';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }

    /**
     * Definimos el formulario con:
     * - Header custom dentro de la card (View component)
     * - Email
     * - Password
     * - Remember checkbox
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                View::make('auth.partials.login-heading'),

                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    protected function getEmailFormComponent(): TextInput
    {
        return parent::getEmailFormComponent()
            ->label('Correo Electrónico')
            ->placeholder('admin@recova.com');
    }

    protected function getPasswordFormComponent(): TextInput
    {
        return parent::getPasswordFormComponent()
            ->label('Contraseña')
            ->placeholder('Ingresa tu contraseña');
    }

    protected function getRememberFormComponent(): Checkbox
    {
        return parent::getRememberFormComponent()
            ->label('Recordar');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->label('Iniciar Sesión');
    }

    /**
     * Agregamos el botón de Google OAuth después del formulario.
     * Inyectamos mediante boot() para que se ejecute al renderizar.
     */
    public function boot(): void
    {
        parent::boot();

        $this->registerRenderHook(
            'panels::auth.login.form.after',
            fn (): string => <<<'HTML'
            <div style="margin-top: 0.9rem; display: flex; flex-direction: column; gap: 0.4rem; align-items: stretch;">
                <div style="position: relative; text-align: center; margin-block: 0.3rem;">
                    <div style="position: absolute; top: 50%; left: 0; right: 0; height: 1px; background: rgba(148, 163, 184, 0.3);"></div>
                    <span style="position: relative; background: rgba(15, 23, 42, 0.96); padding-inline: 0.8rem; color: #9ca3af; font-size: 0.8rem;">O continúa con</span>
                </div>
                <a href="/auth/google/redirect"
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
                    Google
                </a>
            </div>
            HTML
        );
    }
}
