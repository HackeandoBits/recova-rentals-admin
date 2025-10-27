<?php

namespace App\Filament\Resources\Interviews\Schemas;

use App\Rules\NoOverlapRule;
use Carbon\Carbon;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class InterviewForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('title')
                ->label('Título')
                ->maxLength(120),

            DateTimePicker::make('start_at')
                ->label('Inicio')
                ->seconds(false)
                ->required(),

            DateTimePicker::make('end_at')
                ->label('Fin')
                ->seconds(false)
                ->required()
                ->rules([
                    // 1) fin > inicio
                    fn ($get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                        $start = $get('start_at');
                        if ($start && $value && Carbon::parse($value)->lte(Carbon::parse($start))) {
                            $fail('La hora de fin debe ser posterior al inicio.');
                        }
                    },
                    // 2) no solapamiento con otras entrevistas (no canceladas)
                    fn ($get, $record) => new NoOverlapRule($get('start_at'), $record?->id,60),
                ]),

            Select::make('status')
                ->label('Estado')
                ->options([
                    'pending'   => 'Pendiente',
                    'confirmed' => 'Confirmada',
                    'cancelled' => 'Cancelada',
                ])
                ->default('pending')
                ->required(),
        ])->columns(2);
    }
}
