<?php

namespace App\Filament\Resources\Interviews\Schemas;

use App\Rules\NoOverlapRule;
use App\Rules\NoOverlapWithBlocks;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;

class InterviewForm
{
    public static function configure(Form $form): Form
    {
        return $form->schema(self::schema())->columns(2);
    }

    public static function schema(): array
    {
        return [
            TextInput::make('title')
                ->label('Título')
                ->maxLength(120),

            Section::make('Datos del Cliente y Pedido')
                ->schema([
                    TextInput::make('customer_name')
                        ->label('Nombre del Cliente')
                        ->required(),
                    TextInput::make('customer_email')
                        ->label('Email')
                        ->email()
                        ->required(),
                    TextInput::make('customer_phone')
                        ->label('Teléfono'),
                    DatePicker::make('event_date')
                        ->label('Fecha del Evento'),
                    TextInput::make('service_type')
                        ->label('Tipo de Servicio'),
                    Textarea::make('order_notes')
                        ->label('Notas del Pedido')
                        ->columnSpanFull(),
                ])
                ->collapsible(),

            Section::make('Items Solicitados')
                ->schema([
                    \Filament\Forms\Components\Repeater::make('items')
                        ->relationship()
                        ->schema([
                            TextInput::make('name')
                                ->label('Producto')
                                ->required(),
                            TextInput::make('quantity')
                                ->label('Cantidad')
                                ->numeric()
                                ->default(1)
                                ->required(),
                            TextInput::make('note')
                                ->label('Nota'),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->addActionLabel('Agregar Item'),
                ])
                ->collapsible(),

            DateTimePicker::make('start_at')
                ->label('Inicio')
                ->seconds(false)
                ->required()
                // No permitir entrevistas en días anteriores al día actual
                ->minDate(fn () => Carbon::today()),

            DateTimePicker::make('end_at')
                ->label('Fin')
                ->seconds(false)
                ->required()
                // La fecha mínima de fin es el inicio (si existe) o, en su defecto, hoy
                ->minDate(fn (callable $get) => $get('start_at') ?? Carbon::today())
                ->rules([
                    // fin > inicio (una sola vez)
                    fn ($get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                        $start = $get('start_at');
                        if ($start && $value && Carbon::parse($value)->lte(Carbon::parse($start))) {
                            $fail('La hora de fin debe ser posterior al inicio.');
                        }
                    },
                    fn ($get, $record) => new NoOverlapRule($get('start_at'), $record?->id, 60),
                    fn ($get) => new NoOverlapWithBlocks($get('start_at'), (int) env('OWNER_CAL_USER_ID', 1)),
                ]),

            Select::make('status')
                ->label('Estado')
                ->options([
                    'pending' => 'Pendiente',
                    'confirmed' => 'Confirmada',
                    'cancelled' => 'Cancelada',
                ])
                ->default('pending')
                ->required(),
        ];
    }
}
