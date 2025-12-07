<?php

namespace App\Filament\Pages;

use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class Profile extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-user-circle';

    protected static bool $shouldRegisterNavigation = false;

    protected static string $view = 'filament.pages.profile';

    protected static ?string $navigationLabel = 'Mi Perfil';

    protected static ?string $title = 'Mi Perfil';

    public function getSubheading(): ?string
    {
        return 'Administra tu información personal y seguridad.';
    }

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill(auth()->user()->attributesToArray());
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                // ============= BLOQUE 1: INFORMACIÓN PERSONAL ============
                Section::make('Información Personal')
                    ->description('Actualiza los datos de tu perfil e información de contacto')
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre Completo')
                            ->required(),

                        TextInput::make('email')
                            ->label('Correo Electrónico')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true),

                        TextInput::make('dni')
                            ->label('DNI')
                            ->numeric(),

                        TextInput::make('whatsapp')
                            ->label('WhatsApp'),
                    ])
                    ->columns(1)
                    ->columnSpan(1),

                // ============= BLOQUE 2: CAMBIAR CONTRASEÑA ============
                Section::make('Cambiar Contraseña')
                    ->description('Actualiza tu contraseña para mantener tu cuenta segura.')
                    ->schema([
                        TextInput::make('current_password')
                            ->label('Contraseña Actual')
                            ->password()
                            ->revealable()
                            ->requiredWith('new_password')   // si quiere cambiarla, pide la actual
                            ->rule('current_password'),       // valida contra la contraseña del usuario

                        TextInput::make('new_password')
                            ->label('Nueva Contraseña')
                            ->password()
                            ->revealable()
                            ->rule(Password::default()),      // misma regla que ya usabas

                        TextInput::make('new_password_confirmation')
                            ->label('Confirmar Nueva Contraseña')
                            ->password()
                            ->revealable()
                            ->same('new_password')
                            ->requiredWith('new_password'),

                        \Filament\Forms\Components\Actions::make([
                            \Filament\Forms\Components\Actions\Action::make('save')
                                ->label('Guardar Cambios')
                                ->action('submit')
                                ->extraAttributes([
                                    'class' => 'rr-profile-save-btn',
                                    'id' => 'rr-profile-save-btn-id' // ID ÚNICO para asegurar estilos CSS
                                ]),
                        ])->alignment(\Filament\Support\Enums\Alignment::Center),
                    ])
                    ->columns(1)
                    ->columnSpan(1), // todos los campos de password a una sola columna
            ])
            ->columns([
                'default' => 1,
                'md' => 2,
            ])
            ->statePath('data');
    }



    public function submit(): void
    {
        $data = $this->form->getState();

        $user = auth()->user();

        $user->fill([
            'name' => $data['name'],
            'email' => $data['email'],
            'dni' => $data['dni'],
            'whatsapp' => $data['whatsapp'],
        ]);

        if (!empty($data['new_password'])) {
            $user->password = Hash::make($data['new_password']);
        }

        $user->save();

        Notification::make()
            ->success()
            ->title('Perfil actualizado correctamente')
            ->send();

        $this->form->fill($user->attributesToArray());
    }
}
