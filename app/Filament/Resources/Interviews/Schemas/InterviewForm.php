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
                ->maxLength(120)
                ->columnSpanFull(),

            Section::make('Datos del Cliente y Pedido')
                ->schema([
                    \Filament\Forms\Components\Grid::make(2)
                        ->schema([
                            TextInput::make('customer_name')
                                ->label('Nombre del Cliente')
                                ->required(),
                            TextInput::make('customer_email')
                                ->label('Email')
                                ->email()
                                ->required(),
                        ]),
                    \Filament\Forms\Components\Grid::make(2)
                        ->schema([
                            TextInput::make('customer_phone')
                                ->label('Teléfono'),
                            DatePicker::make('event_date')
                                ->label('Fecha del Evento'),
                        ]),

                    Textarea::make('order_notes')
                        ->label('Notas del Pedido')
                        ->rows(1)
                        ->columnSpanFull(),
                ])
                ->collapsible()
                ->compact(),

            Section::make('Productos Solicitados')
                ->schema([
                    \Filament\Forms\Components\Repeater::make('items')
                        ->hiddenLabel()
                        ->relationship()
                        ->reorderable(false)
                        ->deletable(false) // Desactivar borrado estándar para quitar cabecera
                        ->schema([
                            TextInput::make('name')
                                ->hiddenLabel()
                                ->placeholder('Producto')
                                ->required()
                                ->datalist(\App\Models\InterviewItem::query()->distinct()->pluck('name')->toArray())
                                ->columnSpan(6),
                            TextInput::make('quantity')
                                ->hiddenLabel()
                                ->placeholder('Cant.')
                                ->numeric()
                                ->default(1)
                                ->required()
                                ->columnSpan(2),
                            TextInput::make('note')
                                ->hiddenLabel()
                                ->placeholder('Nota')
                                ->columnSpan(3),
                            \Filament\Forms\Components\Actions::make([
                                \Filament\Forms\Components\Actions\Action::make('delete')
                                    ->icon('heroicon-m-trash')
                                    ->color('danger')
                                    ->iconButton() // Solo icono, sin fondo
                                    ->label(null)
                                    ->tooltip('Eliminar')
                                    ->action(function ($component) {
                                        // Navigate to the Actions component (parent of the Action)
                                        $actionsComponent = $component->getParentComponent();
                                        // The container for the repeater row (parent of Actions)
                                        $itemContainer = $actionsComponent->getParentComponent();
                                        // The Repeater component itself (parent of the item container)
                                        $repeater = $itemContainer->getParentComponent();

                                        // State path of the item, e.g., 'items.0' or 'items.uuid-1234'
                                        $itemStatePath = $itemContainer->getStatePath();
                                        $segments = explode('.', $itemStatePath);
                                        $key = end($segments);

                                        // Delete the specific item from the repeater
                                        $repeater->deleteItem($key);
                                    }),
                            ])
                                ->columnSpan(1)
                                ->verticalAlignment(\Filament\Support\Enums\VerticalAlignment::Center),
                        ])
                        ->columns(12)
                        ->defaultItems(0)
                        ->addAction(fn(\Filament\Forms\Components\Actions\Action $action) => $action->label('Agregar Producto')->color('info')),
                ])
                ->collapsible()
                ->compact(),

            Section::make('Fecha y Hora')
                ->schema([
                    \Filament\Forms\Components\Grid::make(4)
                        ->schema([
                            DatePicker::make('start_date')
                                ->label('Fecha Inicio')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    if ($state && $get('start_time')) {
                                        $set('start_at', Carbon::parse($state)->setTimeFromTimeString($get('start_time')));
                                        if ($get('start_at')) {
                                            $start = Carbon::parse($get('start_at'));
                                            $endDateTime = $start->copy()->addHour();
                                            $set('end_date', $endDateTime->toDateString());
                                            $set('end_time', $endDateTime->format('H:i'));
                                            $set('end_at', $endDateTime->toDateTimeString());
                                        }
                                    }
                                })
                                ->afterStateHydrated(function ($component, $state, $record) {
                                    if ($record && $record->start_at) {
                                        $component->state($record->start_at->toDateString());
                                    }
                                }),

                            DatePicker::make('end_date')
                                ->label('Fecha Fin')
                                ->required()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    if ($state && $get('end_time')) {
                                        $set('end_at', Carbon::parse($state)->setTimeFromTimeString($get('end_time')));
                                    }
                                })
                                ->afterStateHydrated(function ($component, $state, $record) {
                                    if ($record && $record->end_at) {
                                        $component->state($record->end_at->toDateString());
                                    }
                                }),

                            Select::make('start_time')
                                ->label('Hora Inicio')
                                ->placeholder('00:00')
                                ->options(self::generateTimeSlots())
                                ->required()
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    if ($state && $get('start_date')) {
                                        $set('start_at', Carbon::parse($get('start_date'))->setTimeFromTimeString($state));
                                        if ($get('start_at')) {
                                            $start = Carbon::parse($get('start_at'));
                                            $endDateTime = $start->copy()->addHour();
                                            $set('end_date', $endDateTime->toDateString());
                                            $set('end_time', $endDateTime->format('H:i'));
                                            $set('end_at', $endDateTime->toDateTimeString());
                                        }
                                    }
                                })
                                ->afterStateHydrated(function ($component, $state, $record) {
                                    if ($record && $record->start_at) {
                                        $component->state($record->start_at->format('H:i'));
                                    }
                                }),

                            Select::make('end_time')
                                ->label('Hora Fin')
                                ->placeholder('00:00')
                                ->options(self::generateTimeSlots())
                                ->required()
                                ->searchable()
                                ->live()
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    if ($state && $get('end_date')) {
                                        $set('end_at', Carbon::parse($get('end_date'))->setTimeFromTimeString($state));
                                    }
                                })
                                ->afterStateHydrated(function ($component, $state, $record) {
                                    if ($record && $record->end_at) {
                                        $component->state($record->end_at->format('H:i'));
                                    }
                                }),
                        ]),
                ])
                ->compact(),

            // Campos ocultos
            \Filament\Forms\Components\Hidden::make('start_at')
                ->dehydrated()
                ->default(fn($record) => $record?->start_at),

            \Filament\Forms\Components\Hidden::make('end_at')
                ->dehydrated()
                ->default(fn($record) => $record?->end_at)
                ->rules([
                    fn($get) => function (string $attribute, $value, \Closure $fail) use ($get) {
                        $start = $get('start_at');
                        if ($start && $value && Carbon::parse($value)->lte(Carbon::parse($start))) {
                            $fail('La hora de fin debe ser posterior al inicio.');
                        }
                    },
                    fn($get, $record) => new NoOverlapRule($get('start_at'), $record?->id, 60),
                    fn($get) => new NoOverlapWithBlocks($get('start_at'), (int) env('OWNER_CAL_USER_ID', 1)),
                ]),

            Select::make('status')
                ->label('Estado')
                ->options([
                    'pending' => 'Pendiente',
                    'confirmed' => 'Confirmada',
                    'cancelled' => 'Cancelada',
                ])
                ->default('confirmed')
                ->required()
                ->columnSpanFull(),
        ];
    }
}
