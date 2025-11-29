<?php

namespace App\Filament\Resources\Interviews\Schemas;

use App\Rules\NoOverlapRule;
use App\Rules\NoOverlapWithBlocks;
use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;

class InterviewForm
{
    public static function configure(Form $form): Form
    {
        return $form->schema(self::schema())->columns(2);
    }

    /**
     * Genera los horarios disponibles de 8:00 a 23:00 en intervalos de 30min
     */
    protected static function generateTimeSlots(): array
    {
        $slots = [];
        for ($hour = 8; $hour <= 23; $hour++) {
            $slots[sprintf('%02d:00', $hour)] = sprintf('%02d:00', $hour);
            if ($hour < 23) { // No agregar 23:30
                $slots[sprintf('%02d:30', $hour)] = sprintf('%02d:30', $hour);
            }
        }
        return $slots;
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
                                ->required()
                                ->datalist(\App\Models\InterviewItem::query()->distinct()->pluck('name')->toArray()),
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

            // FECHA Y HORA SEPARADOS PARA INICIO
            \Filament\Forms\Components\Grid::make(2)
                ->schema([
                    DatePicker::make('start_date')
                        ->label('Fecha de Inicio')
                        ->required()
                        ->minDate(fn () => Carbon::today())
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Recalcular start_at cuando cambia la fecha
                            if ($state && $get('start_time')) {
                                $set('start_at', Carbon::parse($state)->setTimeFromTimeString($get('start_time')));
                                // Auto-calcular end_at (1 hora después)
                                if ($get('start_at')) {
                                    $start = Carbon::parse($get('start_at'));
                                    $endDateTime = $start->copy()->addHour();
                                    $set('end_date', $endDateTime->toDateString());
                                    $set('end_time', $endDateTime->format('H:i'));
                                    $set('end_at', $endDateTime->toDateTimeString());
                                }
                            }
                        }),

                    Select::make('start_time')
                        ->label('Hora de Inicio')
                        ->options(self::generateTimeSlots())
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Recalcular start_at cuando cambia la hora
                            if ($state && $get('start_date')) {
                                $set('start_at', Carbon::parse($get('start_date'))->setTimeFromTimeString($state));
                                // Auto-calcular end_at (1 hora después)
                                if ($get('start_at')) {
                                    $start = Carbon::parse($get('start_at'));
                                    $endDateTime = $start->copy()->addHour();
                                    $set('end_date', $endDateTime->toDateString());
                                    $set('end_time', $endDateTime->format('H:i'));
                                    $set('end_at', $endDateTime->toDateTimeString());
                                }
                            }
                        }),
                ])
                ->columnSpanFull(),

            // Campo oculto que guarda el DateTime real
            \Filament\Forms\Components\Hidden::make('start_at')
                ->dehydrated()
                ->default(fn ($record) => $record?->start_at),

            // FECHA Y HORA SEPARADOS PARA FIN
            \Filament\Forms\Components\Grid::make(2)
                ->schema([
                    DatePicker::make('end_date')
                        ->label('Fecha de Fin')
                        ->required()
                        ->minDate(fn (callable $get) => $get('start_date') ?? Carbon::today())
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Recalcular end_at cuando cambia la fecha
                            if ($state && $get('end_time')) {
                                $set('end_at', Carbon::parse($state)->setTimeFromTimeString($get('end_time')));
                            }
                        }),

                    Select::make('end_time')
                        ->label('Hora de Fin')
                        ->options(self::generateTimeSlots())
                        ->required()
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Recalcular end_at cuando cambia la hora
                            if ($state && $get('end_date')) {
                                $set('end_at', Carbon::parse($get('end_date'))->setTimeFromTimeString($state));
                            }
                        }),
                ])
                ->columnSpanFull(),

            // Campo oculto que guarda el DateTime real
            \Filament\Forms\Components\Hidden::make('end_at')
                ->dehydrated()
                ->default(fn ($record) => $record?->end_at)
                ->rules([
                    // fin > inicio
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
                ->default('confirmed')
                ->required(),
        ];
    }
}
