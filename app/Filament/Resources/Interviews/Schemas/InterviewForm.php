<?php

namespace App\Filament\Resources\Interviews\Schemas;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
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

    /**
     * Genera los horarios disponibles para una fecha dada,
     * excluyendo horarios ocupados por reuniones o bloqueos.
     */
    protected static function getAvailableTimeSlots(?string $date): array
    {
        if (! $date) {
            return [];
        }

        // 1. Generar todos los slots base (16:00 - 21:00)
        $allSlots = [];
        for ($hour = 16; $hour <= 21; $hour++) {
            $allSlots[sprintf('%02d:00', $hour)] = sprintf('%02d:00', $hour);
            if ($hour < 21) {
                $allSlots[sprintf('%02d:30', $hour)] = sprintf('%02d:30', $hour);
            }
        }

        $carbonDate = Carbon::parse($date);
        $startOfDay = $carbonDate->copy()->startOfDay();
        $endOfDay = $carbonDate->copy()->endOfDay();

        // 2. Obtener eventos que ocupan tiempo ese día

        // Reuniones existentes (excluyendo canceladas)
        $interviews = \App\Models\Interview::query()
            ->where('status', '!=', 'cancelled')
            ->where(function ($query) use ($startOfDay, $endOfDay) {
                $query->whereBetween('start_at', [$startOfDay, $endOfDay])
                    ->orWhereBetween('end_at', [$startOfDay, $endOfDay])
                    ->orWhere(function ($q) use ($startOfDay, $endOfDay) {
                        $q->where('start_at', '<', $startOfDay)
                            ->where('end_at', '>', $endOfDay);
                    });
            })
            ->get();

        // Bloqueos de calendario
        $blocks = \App\Models\CalendarBlock::query()
            ->where(function ($query) use ($startOfDay, $endOfDay) {
                $query->whereBetween('starts_at', [$startOfDay, $endOfDay])
                    ->orWhereBetween('ends_at', [$startOfDay, $endOfDay])
                    ->orWhere(function ($q) use ($startOfDay, $endOfDay) {
                        $q->where('starts_at', '<', $startOfDay)
                            ->where('ends_at', '>', $endOfDay);
                    });
            })
            ->get();

        // 3. Filtrar slots ocupados
        return array_filter($allSlots, function ($time) use ($date, $interviews, $blocks) {
            $slotStart = Carbon::parse("$date $time");
            $slotEnd = $slotStart->copy()->addHour(); // Asumimos duración mínima de 1h

            // Verificar colisión con Reuniones
            foreach ($interviews as $interview) {
                // Si el slot se solapa con la reunión
                if ($slotStart->lt($interview->end_at) && $slotEnd->gt($interview->start_at)) {
                    return false;
                }
            }

            // Verificar colisión con Bloqueos
            foreach ($blocks as $block) {
                if ($slotStart->lt($block->ends_at) && $slotEnd->gt($block->starts_at)) {
                    return false;
                }
            }

            return true;
        });
    }

    /**
     * Retorna lista de fechas totalmente bloqueadas (Feriados o Bloqueos Manuales 'All Day')
     * para pintar en el calendario.
     */
    protected static function getFullyBlockedDates(): array
    {
        $ownerId = (int) env('OWNER_CAL_USER_ID', 1);

        return \App\Models\CalendarBlock::query()
            ->whereNull('canceled_at')
            ->where('owner_user_id', $ownerId)
            ->where('is_all_day', true)
            ->where('starts_at', '>=', now()->subMonths(1))
            ->get()
            ->map(function ($block) {
                $dates = [];
                $start = Carbon::parse($block->starts_at);
                $end = Carbon::parse($block->ends_at);

                for ($date = $start; $date->lte($end); $date->addDay()) {
                    $dates[] = $date->toDateString();
                }

                return $dates;
            })
            ->flatten()
            ->unique()
            ->values()
            ->toArray();
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
                        ->deletable(false)
                        ->schema([
                            TextInput::make('name')
                                ->hiddenLabel()
                                ->placeholder('Producto')
                                ->required()
                                ->datalist(fn () => \App\Models\InterviewItem::query()->distinct()->pluck('name')->toArray())
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
                                    ->iconButton()
                                    ->label(null)
                                    ->tooltip('Eliminar')
                                    ->action(function ($component, $livewire) {
                                        $path = $component->getStatePath();
                                        $uuid = \Illuminate\Support\Str::afterLast($path, '.');
                                        $itemsPath = \Illuminate\Support\Str::beforeLast($path, '.');
                                        $currentItems = data_get($livewire, $itemsPath);
                                        if (is_array($currentItems) && isset($currentItems[$uuid])) {
                                            unset($currentItems[$uuid]);
                                            data_set($livewire, $itemsPath, $currentItems);
                                        }
                                    }),
                            ])
                                ->columnSpan(1)
                                ->verticalAlignment(\Filament\Support\Enums\VerticalAlignment::Center),
                        ])
                        ->columns(12)
                        ->defaultItems(0)
                        ->addAction(fn (\Filament\Forms\Components\Actions\Action $action) => $action->label('Agregar Producto')->color('info')),
                ])
                ->collapsible()
                ->compact(),

            Section::make('Fecha y Hora')
                ->schema([
                    \Filament\Forms\Components\Grid::make(2)
                        ->schema([
                            \Filament\Forms\Components\ViewField::make('start_date')
                                ->view('filament.forms.components.blocked-date-picker')
                                ->viewData([
                                    'blockedDates' => self::getFullyBlockedDates(),
                                ])
                                ->label('Fecha')
                                ->required()
                                ->live() // Important to trigger start_time update
                                ->afterStateUpdated(function ($state, callable $set) {
                                    $set('start_time', null);
                                    $set('start_at', null);
                                    $set('end_at', null);
                                })
                                ->afterStateHydrated(function ($component, $state, $record) {
                                    if ($record && $record->start_at) {
                                        $component->state($record->start_at->toDateString());
                                    }
                                })
                                ->rules([
                                    function () {
                                        return function (string $attribute, $value, \Closure $fail) {
                                            if (! $value) {
                                                return;
                                            }

                                            $date = \Carbon\Carbon::parse($value);
                                            // Validate again backend-side just in case
                                            $ownerId = (int) env('OWNER_CAL_USER_ID', 1);

                                            $blockedDay = \App\Models\CalendarBlock::query()
                                                ->whereNull('canceled_at')
                                                ->where('owner_user_id', $ownerId)
                                                ->where('is_all_day', true)
                                                ->get()
                                                ->contains(function ($block) use ($date) {
                                                    $start = \Carbon\Carbon::parse($block->starts_at)->startOfDay();
                                                    $end = \Carbon\Carbon::parse($block->ends_at)->endOfDay();

                                                    return $date->betweenIncluded($start, $end);
                                                });

                                            if ($blockedDay) {
                                                $fail('Esta fecha está bloqueada.');
                                            }
                                        };
                                    },
                                ]),

                            Select::make('start_time')
                                ->label('Hora Inicio')
                                ->placeholder('Seleccionar hora...')
                                ->options(function (\Filament\Forms\Get $get) {
                                    return self::getAvailableTimeSlots($get('start_date'));
                                })
                                ->required()
                                ->live()
                                ->disabled(fn (\Filament\Forms\Get $get) => ! $get('start_date'))
                                ->afterStateUpdated(function ($state, callable $set, callable $get) {
                                    if ($state && $get('start_date')) {
                                        $start = Carbon::parse($get('start_date'))->setTimeFromTimeString($state);
                                        $end = $start->copy()->addHour();

                                        $set('start_at', $start->toDateTimeString());
                                        $set('end_at', $end->toDateTimeString());
                                    }
                                })
                                ->afterStateHydrated(function ($component, $state, $record) {
                                    if ($record && $record->start_at) {
                                        $component->state($record->start_at->format('H:i'));
                                    }
                                }),
                        ]),
                ])
                ->compact(),

            \Filament\Forms\Components\Hidden::make('start_at')
                ->dehydrated()
                ->default(fn ($record) => $record?->start_at),

            \Filament\Forms\Components\Hidden::make('end_at')
                ->dehydrated()
                ->default(fn ($record) => $record?->end_at),

            Select::make('channel')
                ->label('Canal')
                ->options([
                    'whatsapp' => 'WhatsApp',
                    'physical_meeting' => 'Reunión Física',
                ])
                ->default('physical_meeting')
                ->required()
                ->columnSpanFull(),

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
