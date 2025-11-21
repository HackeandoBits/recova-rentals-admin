<?php

namespace App\Filament\Resources\CalendarBlocks\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;     // <-- igual que Interview
use Illuminate\Support\Carbon;

class CalendarBlockForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Título')
                ->required()
                ->maxLength(120),

            Select::make('kind')
                ->label('Tipo')
                ->options([
                    'manual' => 'Manual',
                    'mantenimiento' => 'Mantenimiento',
                    'feriado' => 'Feriado',
                    'otro' => 'Otro',
                ])
                ->default('manual')
                ->native(false),

            Textarea::make('reason')
                ->label('Motivo')
                ->rows(2),

            // Toggle custom: usás Schema, no Toggle de Forms; resolvemos con callback al cambiar 'is_all_day'
            // Si tenés tu propio componente Toggle en este esquema, sustituilo.
            // Si NO, podés usar un Select booleano simple:
            Select::make('is_all_day')
                ->label('Día completo')
                ->options([0 => 'No', 1 => 'Sí'])
                ->default(0)
                ->reactive()
                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                    if ($state && $get('starts_at')) {
                        $set('ends_at', Carbon::parse($get('starts_at'))->endOfDay());
                    }
                }),

            DateTimePicker::make('starts_at')
                ->label('Desde')
                ->seconds(false)
                ->required()
                // No permitir bloquear días anteriores al día actual
                ->minDate(fn () => Carbon::today()),

            DateTimePicker::make('ends_at')
                ->label('Hasta')
                ->seconds(false)
                ->required()
                ->rule('after:starts_at')
                // La fecha mínima de fin es el inicio (si existe) o, en su defecto, hoy
                ->minDate(fn (callable $get) => $get('starts_at') ?? Carbon::today()),
        ])->columns(2);
    }
}
