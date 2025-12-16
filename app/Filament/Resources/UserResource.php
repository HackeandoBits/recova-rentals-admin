<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $modelLabel = 'Usuario';

    public static function getEloquentQuery(): \Illuminate\Database\Eloquent\Builder
    {
        $query = parent::getEloquentQuery();

        if (auth()->check() && ! auth()->user()->isSuperAdmin()) {
            // Admins solo ven usuarios normales (role = user)
            // Y definitivamente no ven al super admin (id 1)
            $query->where('role', 'user')->where('id', '!=', 1);
        }

        return $query;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('email')
                    ->label('Correo Electrónico')
                    ->email()
                    ->required()
                    ->maxLength(255),
                Forms\Components\Select::make('role')
                    ->label('Rol')
                    ->options(fn () => auth()->user()->isSuperAdmin() 
                        ? [
                            'admin' => 'Administrador',
                            'user' => 'Usuario',
                        ]
                        : [
                            'user' => 'Usuario',
                        ]
                    )
                    ->required()
                    ->default('user')
                    // Si no es super admin, deshabilitar o ocultar para forzar 'user'?
                    // El requerimiento dice "CRUD sobre los usuarios nomas", asi que solo pueden crear usuarios.
                    // Al dejar solo la opcion 'user', ya restringimos.
                    ->disabled(fn () => ! auth()->user()->isSuperAdmin())
                    ->dehydrated(), // Para que se guarde el valor aunque esté disabled
                Forms\Components\TextInput::make('password')
                    ->label('Contraseña')
                    ->password()
                    ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                    ->dehydrated(fn ($state) => filled($state))
                    ->required(fn (string $context): bool => $context === 'create'),
                Forms\Components\TextInput::make('dni')
                    ->label('DNI')
                    ->maxLength(255),
                Forms\Components\TextInput::make('whatsapp')
                    ->label('WhatsApp')
                    ->maxLength(255),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('Correo')
                    ->searchable(),
                Tables\Columns\TextColumn::make('role')
                    ->label('Rol')
                    ->badge()
                    ->formatStateUsing(fn (string $state, User $record): string => $record->isSuperAdmin() ? 'Super Admin' : match ($state) {
                        'admin' => 'Administrador',
                        'user' => 'Usuario',
                        default => $state,
                    })
                    ->color(fn (string $state, User $record): string => $record->isSuperAdmin() ? 'info' : match ($state) {
                        'admin' => 'success',
                        'user' => 'gray',
                        default => 'warning',
                    }),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registrado')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
        ];
    }
}
