<?php

namespace App\Filament\Pages\Auth;

use Filament\Actions\Action;
use Filament\Forms\ComponentContainer;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\View;
use Filament\Forms\Form;
use Filament\Pages\Auth\Login as BaseLogin;

class Login extends BaseLogin
{
    /**
     * Ocultamos el heading/subheading que Filament muestra
     * arriba del formulario.
     */
    public function getHeading(): string
    {
        return '';
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    /**
     * Definimos el formulario completo:
     * - Header dentro de la card (View)
     * - Email
     * - Password
     * - Remember me
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
            ->statePath('data'); // igual que hace Filament internamente
    }

    // ===== Campos del formulario (como ya los tenías) =====

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

    // Usamos Component para evitar el warning del tipo
    protected function getRememberFormComponent(): Component
    {
        /** @var Checkbox $component */
        $component = parent::getRememberFormComponent();

        return $component->label('Recordarme');
    }

    protected function getLoginFormAction(): Action
    {
        return parent::getLoginFormAction()
            ->label('Iniciar Sesión');
    }
}
