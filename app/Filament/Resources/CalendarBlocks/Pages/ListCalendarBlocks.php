<?php

namespace App\Filament\Resources\CalendarBlocks\Pages;

use App\Filament\Resources\CalendarBlocks\CalendarBlockResource;
use App\Jobs\SyncBlocksRangeJob;
use App\Models\CalendarBlock;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class ListCalendarBlocks extends ListRecords
{
    use \App\Filament\Traits\PersistsTableConfig;

    protected static string $resource = CalendarBlockResource::class;

    protected static ?string $title = 'Listado de Bloqueos';

    protected function getHeaderActions(): array
    {
        return [

            Action::make('generarBloqueos')
                ->label('Generar bloqueos')
                ->icon('heroicon-o-no-symbol')
                ->color('primary')
                ->visible(fn () => auth()->user()->can('create', CalendarBlock::class))
                ->modalWidth('3xl')
                ->modalSubmitAction(fn (\Filament\Actions\StaticAction $action) => $action->label('Enviar')->extraAttributes([
                    'wire:target' => 'callMountedAction',
                ]))
                ->modalCancelAction(fn (\Filament\Actions\StaticAction $action) => $action->label('Cancelar')->color('danger')->extraAttributes([
                    'wire:target' => 'callMountedAction',
                ]))
                ->form([
                    // Selector de modo (mutuamente excluyente)
                    Radio::make('mode')
                        ->label('Modo de creación')
                        ->options([
                            'range' => 'Por rango de fechas',
                            'days' => 'Por días de la semana (próximas N semanas)',
                        ])
                        ->inline()
                        ->default('range')
                        ->required()
                        ->live()
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state === 'range') {
                                // limpiar campos del modo days
                                $set('dias', null);
                                $set('semanas', 4);
                                $set('all_day_d', 0);
                                $set('desde_hora_d', null);
                                $set('hasta_hora_d', null);
                            } else { // days
                                // limpiar campos del modo range
                                $set('desde', null);
                                $set('hasta', null);
                                $set('all_day_r', 0);
                                $set('desde_hora_r', null);
                                $set('hasta_hora_r', null);
                            }
                        }),

                    // --- Campos del modo RANGO ---
                    DatePicker::make('desde')
                        ->label('Desde')
                        ->required()
                        ->visible(fn ($get) => $get('mode') === 'range')
                        ->dehydrated(fn ($get) => $get('mode') === 'range')
                        // No permitir seleccionar fechas anteriores a hoy
                        ->minDate(fn () => Carbon::today())
                        ->live(debounce: 500)
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            // Si se selecciona fecha de inicio, copiar a fecha fin por defecto
                            if ($state) {
                                $set('hasta', $state);
                            }
                        }),
                    DatePicker::make('hasta')
                        ->label('Hasta')
                        ->required()
                        ->rule('after_or_equal:desde')
                        ->visible(fn ($get) => $get('mode') === 'range')
                        ->dehydrated(fn ($get) => $get('mode') === 'range')
                        // La fecha mínima de fin es "desde" o, en su defecto, hoy
                        ->minDate(fn (callable $get) => $get('desde')
                            ? Carbon::parse($get('desde'))
                            : Carbon::today()),
                    ToggleButtons::make('all_day_r')
                        ->label('Día completo')
                        ->options([0 => 'No', 1 => 'Sí'])
                        ->inline()
                        ->default(0)
                        ->live()
                        ->visible(fn ($get) => $get('mode') === 'range')
                        ->dehydrated(fn ($get) => $get('mode') === 'range')
                        ->afterStateUpdated(function ($state, callable $set, callable $get) {
                            if ($state == 1) {
                                // Al activar día completo, igualar fechas si ya hay "desde"
                                $desde = $get('desde');
                                if ($desde) {
                                    $set('hasta', $desde);
                                }
                            }
                        }),
                    TimePicker::make('desde_hora_r')
                        ->label('Hora inicio')
                        ->seconds(false)
                        ->required(fn ($get) => $get('mode') === 'range' && ! ((bool) $get('all_day_r')))
                        ->visible(fn ($get) => $get('mode') === 'range' && ! ((bool) $get('all_day_r')))
                        ->dehydrated(fn ($get) => $get('mode') === 'range' && ! ((bool) $get('all_day_r')))
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                // Al poner hora inicio, sugerir hora fin +1 hora
                                try {
                                    $time = Carbon::createFromFormat('H:i', $state);
                                    $set('hasta_hora_r', $time->addHour()->format('H:i'));
                                } catch (\Exception $e) {
                                    // Ignorar si el formato no es válido aún
                                }
                            }
                        }),
                    TimePicker::make('hasta_hora_r')
                        ->label('Hora fin')
                        ->seconds(false)
                        ->rule('after:desde_hora_r')
                        ->required(fn ($get) => $get('mode') === 'range' && ! ((bool) $get('all_day_r')))
                        ->visible(fn ($get) => $get('mode') === 'range' && ! ((bool) $get('all_day_r')))
                        ->dehydrated(fn ($get) => $get('mode') === 'range' && ! ((bool) $get('all_day_r'))),

                    // --- Campos del modo DÍAS ---
                    ToggleButtons::make('dias')
                        ->label('Días (L–D)')
                        ->options([
                            1 => 'Lun',
                            2 => 'Mar',
                            3 => 'Mié',
                            4 => 'Jue',
                            5 => 'Vie',
                            6 => 'Sáb',
                            7 => 'Dom',
                        ])
                        ->inline()
                        ->multiple()
                        ->required(fn ($get) => $get('mode') === 'days')
                        ->visible(fn ($get) => $get('mode') === 'days')
                        ->dehydrated(fn ($get) => $get('mode') === 'days'),
                    TextInput::make('semanas')
                        ->label('Semanas a generar')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(26)
                        ->default(4)
                        ->required(fn ($get) => $get('mode') === 'days')
                        ->visible(fn ($get) => $get('mode') === 'days')
                        ->dehydrated(fn ($get) => $get('mode') === 'days'),
                    ToggleButtons::make('all_day_d')
                        ->label('Día completo')
                        ->options([0 => 'No', 1 => 'Sí'])
                        ->inline()
                        ->default(0)
                        ->live()
                        ->visible(fn ($get) => $get('mode') === 'days')
                        ->dehydrated(fn ($get) => $get('mode') === 'days'),
                    TimePicker::make('desde_hora_d')
                        ->label('Hora inicio')
                        ->seconds(false)
                        ->required(fn ($get) => $get('mode') === 'days' && ! ((bool) $get('all_day_d')))
                        ->visible(fn ($get) => $get('mode') === 'days' && ! ((bool) $get('all_day_d')))
                        ->dehydrated(fn ($get) => $get('mode') === 'days' && ! ((bool) $get('all_day_d')))
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, callable $set) {
                            if ($state) {
                                // Al poner hora inicio, sugerir hora fin +1 hora
                                try {
                                    $time = Carbon::createFromFormat('H:i', $state);
                                    $set('hasta_hora_d', $time->addHour()->format('H:i'));
                                } catch (\Exception $e) {
                                    // Ignorar si el formato no es válido aún
                                }
                            }
                        }),
                    TimePicker::make('hasta_hora_d')
                        ->label('Hora fin')
                        ->seconds(false)
                        ->rule('after:desde_hora_d')
                        ->required(fn ($get) => $get('mode') === 'days' && ! ((bool) $get('all_day_d')))
                        ->visible(fn ($get) => $get('mode') === 'days' && ! ((bool) $get('all_day_d')))
                        ->dehydrated(fn ($get) => $get('mode') === 'days' && ! ((bool) $get('all_day_d'))),

                    // Motivo (aplica a ambos modos)
                    TextInput::make('reason')
                        ->label('Motivo (opcional)')
                        ->maxLength(255),
                ])
                ->action(function (array $data) {
                    $now = now();
                    $rows = [];

                    if ($data['mode'] === 'range') {
                        // —— MODO RANGO ——
                        $today = now()->startOfDay();
                        $desde = Carbon::parse($data['desde'])->startOfDay();
                        $hasta = Carbon::parse($data['hasta'])->endOfDay();
                        $allDay = (bool) ($data['all_day_r'] ?? false);

                        // Asegurarnos de no generar bloques antes de hoy
                        if ($desde->lt($today)) {
                            $desde = $today->copy();
                        }

                        for ($cursor = $desde->copy(); $cursor->lte($hasta); $cursor = $cursor->addDay()) {
                            $start = $allDay
                                ? $cursor->copy()->startOfDay()
                                : $cursor->copy()->setTimeFromTimeString($data['desde_hora_r']);
                            $end = $allDay
                                ? $cursor->copy()->endOfDay()
                                : $cursor->copy()->setTimeFromTimeString($data['hasta_hora_r']);

                            if ($end->gt($start)) {
                                $rows[] = [
                                    'title' => 'Bloqueo',
                                    'kind' => 'manual',
                                    'is_all_day' => $allDay,
                                    'starts_at' => $start,
                                    'ends_at' => $end,
                                    'reason' => $data['reason'] ?? null,
                                    'owner_user_id' => (int) env('OWNER_CAL_USER_ID', 1),
                                    'sync_status' => 'pending',
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            }
                        }
                    } else {
                        // —— MODO DÍAS (PRÓXIMAS N SEMANAS) ——
                        $semanas = (int) ($data['semanas'] ?? 4);
                        $allDay = (bool) ($data['all_day_d'] ?? false);

                        // UI usa: 1=Lun, 2=Mar, 3=Mié, 4=Jue, 5=Vie, 6=Sáb, 7=Dom
                        // Carbon usa: 0=Dom, 1=Lun, 2=Mar, 3=Mié, 4=Jue, 5=Vie, 6=Sáb
                        // Conversión correcta:
                        // UI 1 (Lun) → Carbon 1 (Lun): se queda igual
                        // UI 2-6 (Mar-Sáb) → Carbon 2-6: se quedan igual
                        // UI 7 (Dom) → Carbon 0 (Dom): convertir a 0

                        // DEBUG: Ver qué días vienen del formulario
                        \Log::info('GENERAR BLOQUEOS - Días recibidos:', ['dias_raw' => $data['dias'] ?? []]);

                        $diasElegidos = collect($data['dias'] ?? [])
                            ->map(fn ($d) => $d == 7 ? 0 : (int) $d)
                            ->values();

                        \Log::info('GENERAR BLOQUEOS - Días convertidos:', ['dias_carbon' => $diasElegidos->toArray()]);

                        if ($diasElegidos->isEmpty()) {
                            Notification::make()
                                ->title('Elegí al menos un día.')
                                ->warning()
                                ->duration(4000)
                                ->send();

                            return;
                        }

                        $startWindow = now()->startOfDay();
                        $endWindow = $startWindow->copy()->addWeeks($semanas)->endOfDay();

                        for ($cursor = $startWindow->copy(); $cursor->lte($endWindow); $cursor = $cursor->addDay()) {
                            if (! $diasElegidos->contains($cursor->dayOfWeek)) {
                                continue;
                            }

                            $start = $allDay
                                ? $cursor->copy()->startOfDay()
                                : $cursor->copy()->setTimeFromTimeString($data['desde_hora_d']);
                            $end = $allDay
                                ? $cursor->copy()->endOfDay()
                                : $cursor->copy()->setTimeFromTimeString($data['hasta_hora_d']);

                            if ($end->gt($start)) {
                                $rows[] = [
                                    'title' => 'Bloqueo',
                                    'kind' => 'manual',
                                    'is_all_day' => $allDay,
                                    'starts_at' => $start,
                                    'ends_at' => $end,
                                    'reason' => $data['reason'] ?? null,
                                    'owner_user_id' => (int) env('OWNER_CAL_USER_ID', 1),
                                    'sync_status' => 'pending',
                                    'created_at' => $now,
                                    'updated_at' => $now,
                                ];
                            }
                        }
                    }

                    if (empty($rows)) {
                        Notification::make()
                            ->title('No se generaron bloques (verificá la selección).')
                            ->warning()
                            ->duration(4000)
                            ->send();

                        return;
                    }

                    // Insert masivo sin observers + dedupe por unique (si lo tenés)
                    $inserted = 0;
                    CalendarBlock::withoutEvents(function () use (&$inserted, $rows) {
                        foreach (array_chunk($rows, 500) as $chunk) {
                            $inserted += DB::table('calendar_blocks')->insertOrIgnore($chunk);
                        }
                    });

                    // Sync en 2° plano (ahora síncrono por pedido del usuario)
                    $minStart = (string) collect($rows)->min('starts_at');

                    // Usamos dispatchSync para que se ejecute YA, sin workers
                    SyncBlocksRangeJob::dispatchSync($minStart);

                    // (Código de worker eliminado)

                    Notification::make()
                        ->title("Bloques creados: {$inserted}. La sincronización está en curso.")
                        ->success()
                        ->duration(4000)
                        ->send();
                }),
        ];
    }
}
