<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class Login extends BaseLogin
{
    /**
     * Campos del formulario base (email, password, recordar)
     */
    protected function getEmailFormComponent(): TextInput
    {
        return parent::getEmailFormComponent()
            ->label('Correo Electrónico')
            ->placeholder('recovarentals@gmail.com');
    }

    protected function getPasswordFormComponent(): TextInput
    {
        return parent::getPasswordFormComponent()
            ->label('Contraseña')
            ->placeholder('Ingresa tu contraseña');
    }

    protected function getRememberFormComponent(): Checkbox
    {
        return Checkbox::make('remember')
            ->label('Recordar');
    }

    protected function getAuthenticateFormAction(): Action
    {
        // Usamos la acción base y solo cambiamos el label
        return parent::getAuthenticateFormAction()
            ->label('Iniciar Sesión');
    }

    /**
     * IMPORTANTÍSIMO:
     * Anulamos heading y subheading del simple header
     * (así no se generan dentro del <header> con el logo).
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
     * Ahora sí: el título "Iniciar Sesión" y la frase
     * van DENTRO del form, como primer bloque del schema.
     */
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Placeholder::make('login_header')
                    ->label(null)
                    ->hiddenLabel()        // oculta completamente el label en el DOM
                    ->content(new HtmlString(
                        '<div class="rr-login-heading">
                        <h2 class="rr-login-title">
                            Iniciar Sesión
                        </h2>
                        <p class="rr-login-subtitle">
                            Ingresa tus credenciales para acceder
                        </p>
                    </div>'
                    )),

                $this->getEmailFormComponent(),
                $this->getPasswordFormComponent(),
                $this->getRememberFormComponent(),
            ])
            ->statePath('data');
    }

    public function getFooter(): ?HtmlString
    {
        return new HtmlString('
            <div style="margin-top: 1.5rem;">
                <div style="display: flex; align-items: center; margin-bottom: 1rem;">
                    <div style="flex: 1; height: 1px; background: rgba(148, 163, 184, 0.3);"></div>
                    <span style="padding: 0 1rem; font-size: 0.875rem; color: rgba(148, 163, 184, 0.7);">
                        o continúa con
                    </span>
                    <div style="flex: 1; height: 1px; background: rgba(148, 163, 184, 0.3);"></div>
                </div>

                <a href="' . route('google.login') . '"
                    style="
                        display: flex;
                        align-items: center;
                        justify-content: center;
                        gap: 0.75rem;
                        background: white;
                        color: #1f2937;
                        font-weight: 600;
                        border-radius: 0.5rem;
                        padding: 0.625rem 1rem;
                        border: 1px solid rgba(209, 213, 219, 1);
                        width: 100%;
                        text-decoration: none;
                        transition: all 0.2s ease;
                    "
                    onmouseover="this.style.background=\'#f9fafb\'" onmouseout="this.style.background=\'white\'">
                    <svg style="width: 20px; height: 20px;" viewBox="0 0 24 24">
                        <path fill="#4285F4"
                            d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" />
                        <path fill="#34A853"
                            d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" />
                        <path fill="#FBBC05"
                            d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" />
                        <path fill="#EA4335"
                            d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" />
                    </svg>
                    <span>Continuar con Google</span>
                </a>
            </div>
        ');
    }
}
