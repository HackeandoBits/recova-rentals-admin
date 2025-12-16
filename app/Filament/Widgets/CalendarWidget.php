<?php

namespace App\Filament\Widgets;

use App\Models\Interview;
use Filament\Forms\Components\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Saade\FilamentFullCalendar\Actions\ViewAction;
use Saade\FilamentFullCalendar\Widgets\FullCalendarWidget;

class CalendarWidget extends FullCalendarWidget
{
    public bool $eventClickEnabled = true;

    public function getModel(): string
    {
        return Interview::class;
    }

    public function canCreate(): bool
    {
        // Solo admins pueden crear (según Policy)
        return auth()->user()->can('create', Interview::class);

        // Solo admins pueden crear (según Policy)
        return auth()->user()->can('create', Interview::class);
    }

    public function canEdit(): bool
    {
        return false; // Deshabilitar D&D para todos por ahora (o checkear policy)

        return false; // Deshabilitar D&D para todos por ahora (o checkear policy)
    }

    public function canDelete(): bool
    {
        return false;
    }

    public function config(): array
    {
        return [
            'locale' => 'es',
            'buttonText' => [
                'today' => 'Hoy',
                'month' => 'Mes',
                'week' => 'Semana',
                'day' => 'Día',
                'list' => 'Lista',
            ],
            'dayMaxEvents' => true, // Limitar eventos por día para mantener altura de celdas
            'fixedWeekCount' => false, // No forzar 6 semanas si no son necesarias
            'showNonCurrentDates' => true, // Mostrar días del mes siguiente/anterior para completar semana
            'titleFormat' => [
                'year' => 'numeric',
                'month' => 'long', // Nombre completo del mes
            ],
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            \Filament\Actions\CreateAction::make('createInterview')
                ->label('Crear Reunión')
                ->model(\App\Models\Interview::class)
                ->form([
                    \Filament\Forms\Components\TextInput::make('title')
                        ->label('Título')
                        ->required(),
                    \Filament\Forms\Components\DateTimePicker::make('start_at')
                        ->label('Inicio')
                        ->seconds(false)
                        ->required()
                        ->live() // Hacerlo reactivo
                        ->afterStateUpdated(function ($state, \Filament\Forms\Set $set, \Filament\Forms\Components\DateTimePicker $component) {
                            if ($state) {
                                $start = \Carbon\Carbon::parse($state);
                                // Set End time to +1 hour by default
                                $set('end_at', $start->addHour()->format('Y-m-d H:i:s'));
                                
                                $component->validate();
                            }
                        })
                        ->rules([
                            fn () => function (string $attribute, $value, \Closure $fail) {
                                if (! $value) return;
                                
                                $start = \Carbon\Carbon::parse($value);
                                // Default end is +1 hour if not checking end_at yet, but let's check the immediate slot
                                $end = $start->copy()->addHour(); 
                                
                                // 1. Check All Day Blocks (Feriados, Google All Day)
                                $blockedDay = \App\Models\CalendarBlock::query()
                                    ->where('is_all_day', true)
                                    ->whereDate('starts_at', $start->toDateString())
                                    ->exists();

                                if ($blockedDay) {
                                    $fail('Esta fecha está bloqueada por un evento de día completo.');
                                    return;
                                }

                                // 2. Check Time Overlaps (Partial Blocks)
                                $overlapBlock = \App\Models\CalendarBlock::query()
                                    ->where('is_all_day', false)
                                    ->where('starts_at', '<', $end)
                                    ->where('ends_at', '>', $start)
                                    ->exists();
                                
                                if ($overlapBlock) {
                                    $fail('Horario Bloqueado: coincide con un bloqueo existente.');
                                }
                            },
                        ]),
                    \Filament\Forms\Components\DateTimePicker::make('end_at')
                        ->label('Fin')
                        ->seconds(false)
                        ->required()
                        ->after('start_at'),
                    \Filament\Forms\Components\Select::make('channel')
                        ->label('Canal')
                        ->options([
                            'whatsapp' => 'WhatsApp',
                            'physical_meeting' => 'Reunión Física',
                        ])
                        ->default('physical_meeting')
                        ->required(),
                    \Filament\Forms\Components\Select::make('status')
                        ->options([
                            'pending' => 'Pendiente',
                            'confirmed' => 'Confirmada',
                            'cancelled' => 'Cancelada',
                        ])
                        ->default('confirmed')
                        ->required(),
                ])
                ->before(function (\Filament\Actions\CreateAction $action, array $data) {
                    $start = \Carbon\Carbon::parse($data['start_at']);
                    $end = \Carbon\Carbon::parse($data['end_at']);

                    // 1. Validar superposición con otras entrevistas (que no estén canceladas)
                    $overlapInterview = \App\Models\Interview::query()
                        ->where('status', '!=', 'cancelled')
                        ->where(function ($query) use ($start, $end) {
                            $query->whereBetween('start_at', [$start, $end])
                                ->orWhereBetween('end_at', [$start, $end])
                                ->orWhere(function ($q) use ($start, $end) {
                                    $q->where('start_at', '<=', $start)
                                        ->where('end_at', '>=', $end);
                                });
                        })
                        ->exists();

                    if ($overlapInterview) {
                        \Filament\Notifications\Notification::make()
                            ->title('Conflicto de horario')
                            ->body('Ya existe una reunión programada en este rango.')
                            ->danger()
                            ->persistent()
                            ->send();
                        
                        $action->halt();
                    }

                    // 2. Validar superposición con Bloqueos de Calendario
                    // Lógica simplificada de intersección: (StartA < EndB) y (EndA > StartB)
                    // Hacemos query manual para debug y mayor control
                    $conflictingBlocks = \App\Models\CalendarBlock::query()
                        ->where('starts_at', '<', $end)
                        ->where('ends_at', '>', $start)
                        ->get();

                    if ($conflictingBlocks->isNotEmpty()) {
                        // Doble chequeo en PHP para asegurar (especialmente temas de segundos :00 vs :59)
                        // Filtrar falsos positivos si los hubiera (aunque la query es sólida)
                        $realConflict = $conflictingBlocks->contains(function ($block) use ($start, $end) {
                            $blockStart = \Carbon\Carbon::parse($block->starts_at);
                            $blockEnd = \Carbon\Carbon::parse($block->ends_at);
                            
                            // Si es All Day, aseguramos que cubra todo el día hasta 23:59:59 si es necesario para comparar
                            if ($block->is_all_day) {
                                $blockEnd = $blockEnd->endOfDay(); 
                            }

                            return $blockStart->lt($end) && $blockEnd->gt($start);
                        });

                        if ($realConflict) {
                            \Filament\Notifications\Notification::make()
                                ->title('Horario Bloqueado')
                                ->body('No se puede agendar en un día u horario bloqueado (Google/Manual).')
                                ->danger()
                                ->persistent() // Para que no desaparezca solo
                                ->send();
                            
                            $action->halt();
                        }
                    }
                })
                ->after(function ($livewire) {
                    $livewire->refreshRecords();
                }),


        ];
    }

    public function eventContent(): string
    {
        return <<<'JS'
            function(arg) {
                let title = arg.event.title;
                let description = arg.event.extendedProps.description || '';
                
                // Icons (Heroicons solid)
                // Removed 'mr-1 inline-block' to rely on flex gap and alignment
                const icons = {
                    meeting: '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>', // Clock
                    block: '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636" /></svg>', // Ban
                    google: '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" /></svg>', // Calendar
                    holiday: '<svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3v4M3 5h4M6 17v4m-2-2h4m5-16l2.286 6.857L21 12l-5.714 3.214L18 21l-6-3-6 3 2.286-5.786L3 12l6.857-1.143L12 1z" /></svg>' // Star
                };

                let iconHtml = icons.meeting; // Default to meeting

                if (arg.event.extendedProps.isBlock) {
                    iconHtml = icons.block;
                } else if (arg.event.extendedProps.isHoliday) {
                    iconHtml = icons.holiday;
                } else if (arg.event.extendedProps.isGoogleEvent) {
                    iconHtml = icons.google;
                }

                // Custom HTML structure
                // Use gap-1 for spacing, items-center for vertical alignment
                // Reduced icon size slightly (w-3.5) to match text better
                return {
                    html: `
                        <div class="fc-event-main-frame flex items-center gap-1 px-1 w-full overflow-hidden">
                            <div class="fc-event-icon flex-shrink-0 flex items-center justify-center">${iconHtml}</div>
                            <div class="fc-event-title font-medium truncate min-w-0 flex-1 leading-tight">${title}</div>
                        </div>
                    `
                };
            }
        JS;
    }

    public function fetchEvents(array $fetchInfo): array
    {
        // Fix for Cross-DB Compatibility (SQL Server & MySQL):
        // Parse ISO 8601 dates and format them to standard SQL 'Y-m-d H:i:s'
        $start = \Carbon\Carbon::parse($fetchInfo['start'])->format('Y-m-d H:i:s');
        $end = \Carbon\Carbon::parse($fetchInfo['end'])->format('Y-m-d H:i:s');

        $interviews = Interview::query()
            ->where('start_at', '>=', $start)
            ->where('end_at', '<=', $end)
            ->where('status', '!=', 'pending') // Solo mostrar confirmadas en calendario
            ->get()
            ->map(
                fn (Interview $interview) => [
                    'id' => $interview->id,
                    'title' => $interview->title ?? 'Reunión',
                    'start' => $interview->start_at,
                    'end' => $interview->end_at,
                    'display' => 'list-item', // Mostrar como texto sin fondo
                    'backgroundColor' => 'transparent',
                    'borderColor' => 'transparent',
                    'textColor' => '#ffffff', // Texto blanco para tema oscuro
                    'className' => 'fc-event-transparent', // Clase CSS personalizada
                    'extendedProps' => [
                        'description' => $interview->description,
                        'customer_name' => $interview->customer_name ?? 'N/A',
                        'customer_phone' => $interview->customer_phone ?? '',
                        'status' => $interview->status,
                        'google_event_id' => $interview->google_event_id, // For duplicate checking
                    ],
                ]
            );

        $blocks = \App\Models\CalendarBlock::query()
            ->where('starts_at', '>=', $start)
            ->where('ends_at', '<=', $end)
            ->get()
            ->map(
                fn (\App\Models\CalendarBlock $block) => [
                    'id' => 'block-'.$block->id,
                    'title' => $block->is_all_day
                        ? ($block->title ?? 'Bloqueado')
                        : 'Bloqueado: '.($block->title ?? 'Sin título'),
                    'start' => $block->starts_at,
                    'end' => $block->ends_at,
                    'allDay' => $block->is_all_day,
                    // Ghost Button Style (Navbar-like) via CSS class
                    'className' => 'fc-event-ghost',
                    'backgroundColor' => 'rgba(255, 255, 255, 0.05)', // Fallback
                    'borderColor' => 'rgba(255, 255, 255, 0.1)', // Fallback
                    'textColor' => '#9ca3af', // Gray-400
                    'extendedProps' => [
                        'description' => 'Bloqueo de agenda',
                        'isBlock' => true,
                        'google_event_id' => $block->google_event_id, // For duplicate checking
                    ],
                    'url' => '', // Empty string to bypass plugin's url check
                ]
            );

        // --- GOOGLE CALENDAR FETCH ---
        $googleEvents = [];
        try {
            \Illuminate\Support\Facades\Log::info('CalendarWidget: Fetching Google Events', [
                'start' => $fetchInfo['start'],
                'end' => $fetchInfo['end'],
            ]);

            /** @var \App\Services\GoogleCalendarService $service */
            $service = app(\App\Services\GoogleCalendarService::class);

            // Collect IDs of local events that are already synced to avoid visual duplicates
            // We use 'google_event_id' which matches the ID from Google
            $syncedIds = $interviews->pluck('extendedProps.google_event_id')
                ->merge($blocks->pluck('extendedProps.google_event_id'))
                ->filter()
                ->flip(); // Flip for faster lookup (id => key)

            $rawGoogleEvents = $service->listEvents(
                \Carbon\Carbon::parse($fetchInfo['start']),
                \Carbon\Carbon::parse($fetchInfo['end'])
            );

            \Illuminate\Support\Facades\Log::info('CalendarWidget: Raw Google Events Count: '.count($rawGoogleEvents));

            foreach ($rawGoogleEvents as $gEvent) {
                // If this event is already represented by a local interview or block, skip it
                if ($syncedIds->has($gEvent->getId())) {
                    continue;
                }

                $isAllDay = empty($gEvent->start->dateTime);
                $gStart = $isAllDay ? $gEvent->start->date : \Carbon\Carbon::parse($gEvent->start->dateTime);
                $gEnd = $isAllDay ? $gEvent->end->date : \Carbon\Carbon::parse($gEvent->end->dateTime);

                $googleEvents[] = [
                    'id' => 'gcal-'.$gEvent->getId(),
                    'title' => $gEvent->getSummary() ?? '(Sin título)',
                    'start' => $gStart,
                    'end' => $gEnd,
                    'allDay' => $isAllDay,
                    'backgroundColor' => '#6b7280', // Gray for external events
                    'borderColor' => '#4b5563',
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'description' => $gEvent->getDescription(),
                        'isGoogleEvent' => true,
                        'google_html_link' => $gEvent->getHtmlLink(),
                    ],
                    // Optional: link to open in Google Calendar
                    // 'url' => $gEvent->getHtmlLink(), // REMOVED to prevent redirect
                    // 'url' => null, // Removed completely to prevent 'null' link
                ];
            }

            \Illuminate\Support\Facades\Log::info('CalendarWidget: Processed Google Events Count: '.count($googleEvents));

            // --- GOOGLE HOLIDAYS FETCH ---
            $holidayEvents = $service->listEvents(
                \Carbon\Carbon::parse($fetchInfo['start']),
                \Carbon\Carbon::parse($fetchInfo['end']),
                'es.ar#holiday@group.v.calendar.google.com' // ID for Argentina Holidays
            );

            foreach ($holidayEvents as $hEvent) {
                // Holidays are typically all-day
                $isAllDay = empty($hEvent->start->dateTime);
                $hStart = $isAllDay ? $hEvent->start->date : \Carbon\Carbon::parse($hEvent->start->dateTime);
                $hEnd = $isAllDay ? $hEvent->end->date : \Carbon\Carbon::parse($hEvent->end->dateTime);

                $googleEvents[] = [
                    'id' => 'gholiday-'.$hEvent->getId(),
                    'title' => $hEvent->getSummary() ?? 'Feriado',
                    'start' => $hStart,
                    'end' => $hEnd,
                    'allDay' => true,
                    'backgroundColor' => '#690650ff', // Green/Teal for holidays
                    'borderColor' => '#a71783ff',
                    'textColor' => '#ffffff',
                    'extendedProps' => [
                        'description' => $hEvent->getDescription(),
                        'isHoliday' => true,
                    ],
                    'editable' => false,
                    // 'url' => null,
                ];
            }

            \Illuminate\Support\Facades\Log::info('CalendarWidget: Processed Holiday Events Count: '.count($holidayEvents));

        } catch (\Exception $e) {
            // Log silently or notify? Better to log silently so the whole calendar doesn't break
            \Illuminate\Support\Facades\Log::error('CalendarWidget Google Fetch Error: '.$e->getMessage());
        }

        return array_merge(
            $interviews->values()->all(),
            $blocks->values()->all(),
            $googleEvents
        );
    }

    public function resolveRecord(string|int $key): \Illuminate\Database\Eloquent\Model
    {
        // 1. Bloqueo Local
        if (is_string($key) && str_starts_with($key, 'block-')) {
            $blockId = str_replace('block-', '', $key);

            return \App\Models\CalendarBlock::findOrFail($blockId);
        }

        // 2. Evento de Google (En vivo)
        if (is_string($key) && str_starts_with($key, 'gcal-')) {
            $googleId = str_replace('gcal-', '', $key);
            
            // Buscar evento real en Google
            /** @var \App\Services\GoogleCalendarService $service */
            $service = app(\App\Services\GoogleCalendarService::class);
            $gEvent = $service->getEvent($googleId);

            if (! $gEvent) {
                // Si falla, retornamos un modelo vacío o lanzamos 404.
                // Filament espera un Model, si lanzamos 404 muestra error.
                abort(404, 'Evento de Google no encontrado');
            }

            // Crear modelo transitorio (no guardado en DB) para que el ViewAction lo muestre
             $isAllDay = empty($gEvent->start->dateTime);
             $start = $isAllDay ? \Carbon\Carbon::parse($gEvent->start->date) : \Carbon\Carbon::parse($gEvent->start->dateTime);
             $end = $isAllDay ? \Carbon\Carbon::parse($gEvent->end->date) : \Carbon\Carbon::parse($gEvent->end->dateTime);

            $description = $gEvent->getDescription();
            // Limpiar ID interno si existe (formato "Texto...\nID: 123")
            if ($description) {
                $description = preg_replace('/\nID: \d+$/', '', $description);
            }

            $block = new \App\Models\CalendarBlock([
                'title' => $gEvent->getSummary() ?? '(Sin título)',
                'starts_at' => $start,
                'ends_at' => $end,
                'is_all_day' => $isAllDay,
                'kind' => 'otro',
                'reason' => $description,
            ]);
            
            // Marcar como externo para la UI
            $block->is_google_event = true; 
            $block->google_html_link = $gEvent->getHtmlLink();

            return $block;
        }

        // 3. Feriado (En vivo)
        if (is_string($key) && str_starts_with($key, 'gholiday-')) {
             // Lógica simplificada para feriados (generalmente no se clickean, pero por si acaso)
             return new \App\Models\CalendarBlock([
                'title' => 'Feriado',
                'kind' => 'feriado',
             ]);
        }

        // 4. Interview (ID numérico)
        return Interview::findOrFail($key);
    }

    protected function headerActions(): array
    {
        return [];
    }

    protected function modalActions(): array
    {
        return [
            \Saade\FilamentFullCalendar\Actions\EditAction::make()
                ->visible(fn ($record) => $record instanceof Interview && auth()->user()->can('update', $record))
                ->form(fn ($form) => \App\Filament\Resources\Interviews\Schemas\InterviewForm::configure($form)->getSchema())
                ->modalHeading('Editar Reunión')
                ->modalSubmitActionLabel('Guardar')
                ->modalCancelActionLabel('Cancelar')
                ->iconButton()
                ->icon('heroicon-o-pencil'),

            \Saade\FilamentFullCalendar\Actions\DeleteAction::make()
                ->visible(fn ($record) => $record instanceof Interview && auth()->user()->can('delete', $record))
                ->modalHeading('Eliminar Reunión')
                ->modalDescription('¿Estás seguro que deseas eliminar esta reunión?')
                ->modalSubmitActionLabel('Eliminar')
                ->modalCancelActionLabel('Cancelar')
                ->iconButton()
                ->icon('heroicon-o-trash'),

            ViewAction::make('view')
                ->modalHeading(fn ($record) => $record instanceof \App\Models\CalendarBlock
                    ? ($record->is_google_event ? '📅 Evento de Google' : '🚫 Bloqueo de Agenda')
                    : ($record->title ?? 'Reunión'))
                ->modalWidth('xs')
                ->infolist(function ($record) {
                    // ... (INFO LIST CONTENT SAME AS BEFORE) ...
                    if ($record instanceof \App\Models\CalendarBlock) {
                        return [
                            \Filament\Infolists\Components\TextEntry::make('title')
                                ->label('Título'),
                            \Filament\Infolists\Components\TextEntry::make('formatted_date')
                                ->label('Fecha')
                                ->state(fn ($record) => \Carbon\Carbon::parse($record->starts_at)->translatedFormat('l, j \\d\\e F').' • '.
                                    \Carbon\Carbon::parse($record->starts_at)->format('H:i').' – '.
                                    \Carbon\Carbon::parse($record->ends_at)->format('H:i'))
                                ->icon('heroicon-o-clock'),
                            \Filament\Infolists\Components\TextEntry::make('kind')
                                ->label('Tipo')
                                ->formatStateUsing(fn ($state) => match ($state) {
                                    'manual' => 'Manual',
                                    'mantenimiento' => 'Mantenimiento',
                                    'feriado' => 'Feriado',
                                    'otro' => 'Otro',
                                    default => ucfirst($state),
                                }),
                            \Filament\Infolists\Components\TextEntry::make('reason')
                                ->label(fn ($record) => ($record->is_google_event || $record->kind === 'otro') ? 'Descripción' : 'Razón del bloqueo')
                                ->visible(fn ($state) => ! empty($state))
                                ->columnSpanFull(),
                        ];
                    }

                    // Interview fields
                    return [
                        \Filament\Infolists\Components\TextEntry::make('formatted_date')
                            ->label('Fecha')
                            ->state(fn ($record) => \Carbon\Carbon::parse($record->start_at)->translatedFormat('l, j \\d\\e F').' • '.
                                \Carbon\Carbon::parse($record->start_at)->format('H:i').' – '.
                                \Carbon\Carbon::parse($record->end_at)->format('H:i'))
                            ->icon('heroicon-o-clock'),
                        \Filament\Infolists\Components\TextEntry::make('customer_name')
                            ->label('Cliente')
                            ->icon('heroicon-o-user'),
                        \Filament\Infolists\Components\TextEntry::make('customer_phone')
                            ->label('Teléfono')
                            ->icon('heroicon-o-phone')
                            ->visible(fn ($record) => ! empty($record->customer_phone))
                            ->suffixAction(
                                \Filament\Infolists\Components\Actions\Action::make('whatsapp')
                                    ->icon('heroicon-o-chat-bubble-left-right')
                                    ->url(fn ($record) => $record->customer_phone
                                        ? 'https://wa.me/'.preg_replace('/[^0-9]/', '', $record->customer_phone)
                                        : null)
                                    ->openUrlInNewTab()
                            ),
                        \Filament\Infolists\Components\TextEntry::make('order_notes')
                            ->label('Notas')
                            ->visible(fn ($record) => ! empty($record->order_notes))
                            ->columnSpanFull(),
                        \Filament\Infolists\Components\TextEntry::make('items_summary')
                            ->label('Items Solicitados')
                            ->state(fn ($record) => $record->items->map(fn ($item) => "• {$item->quantity}x {$item->name}")->join('<br>'))
                            ->html()
                            ->visible(fn ($record) => $record->items()->exists())
                            ->columnSpanFull()
                            ->color('gray'),
                    ];
                })
                ->modalFooterActions(function ($record, $livewire) {
                    if ($record instanceof \App\Models\CalendarBlock) {
                        return [
                            \Filament\Actions\Action::make('delete_block')
                                ->label('Eliminar Bloqueo')
                                ->icon('heroicon-o-trash')
                                ->color('danger')
                                ->requiresConfirmation()
                                // No permitir borrar eventos de Google live desde aquí (requiere sync)
                                ->visible(fn () => auth()->user()->can('delete', $record) && ! $record->is_google_event)
                                ->modalHeading('Eliminar Bloqueo')
                                ->modalDescription('¿Estás seguro que deseas eliminar este bloqueo?')
                                ->action(function ($record, $livewire) {
                                    $record->delete();
                                    $livewire->refreshRecords();
                                    $livewire->dispatch('close-modal', id: 'view-event');
                                }),
                        ];
                    }

                    return [
                        \Filament\Actions\Action::make('edit')
                            ->label('Editar')
                            ->icon('heroicon-o-pencil')
                            ->color('primary')
                            ->visible(fn () => auth()->user()->can('update', $record))
                            ->fillForm(fn ($record) => $record->attributesToArray())
                            ->form(fn ($form) => $form->schema(\App\Filament\Resources\Interviews\Schemas\InterviewForm::schema()))
                            ->action(function (array $data, $record, $livewire) {
                                $record->update($data);
                                $livewire->refreshRecords();
                                $livewire->dispatch('close-modal', id: 'view-event');

                                \Filament\Notifications\Notification::make()
                                    ->title('Reunión actualizada')
                                    ->success()
                                    ->send();
                            }),
                        \Filament\Actions\Action::make('delete_interview')
                            ->label('Eliminar')
                            ->icon('heroicon-o-trash')
                            ->color('danger')
                            ->requiresConfirmation()
                            ->visible(fn () => auth()->user()->can('delete', $record))
                            ->modalHeading('Eliminar Reunión')
                            ->modalDescription('¿Estás seguro que deseas eliminar esta reunión?')
                            ->action(function ($record, $livewire) {
                                $record->delete();
                                $livewire->refreshRecords();
                                $livewire->dispatch('close-modal', id: 'view-event');
                            }),
                    ];
                }),
        ];
    }

    protected function getInterviewForm(): array
    {
        return [
            TextInput::make('title')
                ->label('Título')
                ->disabled(),
            DateTimePicker::make('start_at')
                ->label('Inicio')
                ->disabled(),
            DateTimePicker::make('end_at')
                ->label('Fin')
                ->disabled(),
            TextInput::make('customer_name')
                ->label('Cliente')
                ->disabled(),
            TextInput::make('customer_email')
                ->label('Email')
                ->disabled(),
            TextInput::make('customer_phone')
                ->label('Teléfono')
                ->disabled()
                ->suffixAction(
                    Action::make('whatsapp')
                        ->icon('heroicon-o-chat-bubble-left-right')
                        ->url(fn ($record) => $record->customer_phone
                            ? 'https://wa.me/'.preg_replace('/[^0-9]/', '', $record->customer_phone)
                            : null)
                        ->openUrlInNewTab()
                        ->visible(fn ($record) => ! empty($record->customer_phone))
                ),
            Textarea::make('order_notes')
                ->label('Notas del Pedido')
                ->disabled()
                ->columnSpanFull(),
        ];
    }

    protected function getWhatsappUrl(string $phone): string
    {
        // Clean phone number
        $phone = preg_replace('/[^0-9]/', '', $phone);
        // Add 549 if missing for Argentine numbers (simplified logic)
        if (str_starts_with($phone, '11') || (strlen($phone) == 10 && ! str_starts_with($phone, '54'))) {
            $phone = '549'.$phone;
        }

        return "https://wa.me/{$phone}";
    }

    protected function isArgentineNumber(string $phone): bool
    {
        $phone = preg_replace('/[^0-9]/', '', $phone);

        // Check if starts with 54 or has typical Argentine length/format
        return str_starts_with($phone, '54') || str_starts_with($phone, '11') || strlen($phone) >= 10;
    }
}
