<?php

namespace App\Filament\Pages\Auth;

use Filament\Auth\Pages\Login as BaseLogin;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Checkbox;
use Filament\Actions\Action;

class Login extends BaseLogin
{
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
        return parent::getRememberFormComponent()
            ->label('Recordar');
    }

    protected function getAuthenticateFormAction(): Action
    {
        return parent::getAuthenticateFormAction()
            ->label('Iniciar Sesión');
    }

    public function getHeading(): string
    {
        return 'Iniciar Sesión';
    }

    public function getSubHeading(): ?string
    {
        return 'Ingresa tus credenciales para acceder';
    }
}
