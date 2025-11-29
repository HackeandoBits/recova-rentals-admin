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
}
