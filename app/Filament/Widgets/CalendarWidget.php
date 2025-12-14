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
    }

    public function canEdit(): bool
    {
        return false; // Deshabilitar D&D para todos por ahora (o checkear policy)
    }

    public function canDelete(): bool
    {
        return false;
    }

    public function config(): array
    {
        return [
            'dayMaxEvents' => true, // Limitar eventos por día para mantener altura de celdas
            'fixedWeekCount' => false, // No forzar 6 semanas si no son necesarias
            'showNonCurrentDates' => true, // Mostrar días del mes siguiente/anterior para completar semana
            'titleFormat' => [
                'year' => 'numeric',
                'month' => 'long', // Nombre completo del mes
            ],
        ];
    }

    public function fetchEvents(array $fetchInfo): array
    {
        // Fix for Cross-DB Compatibility (SQL Server & MySQL):
        // Parse ISO 8601 dates and format them to standard SQL 'Y-m-d H:i:s'
        $start = \Carbon\Carbon::parse($fetchInfo['start'])->format('Y-m-d H:i:s');
        $end = \Carbon\Carbon::parse($fetchInfo['end'])->format('Y-m-d H:i:s');

        $interviews = Interview::query()
            ->where('start_at', '>=', $fetchInfo['start'])
            ->where('end_at', '<=', $fetchInfo['end'])
            ->where('status', '!=', 'pending') // Solo mostrar confirmadas en calendario
            ->get()
            ->map(
                fn (Interview $interview) => [
                    'id' => $interview->id,
                    'title' => '🕒 '.($interview->title ?? 'Reunión'),
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
                        ? '🚫 '.($block->title ?? 'Bloqueado')
                        : '🚫 Bloqueado: '.($block->title ?? 'Sin título'),
                    'start' => $block->starts_at,
                    'end' => $block->ends_at,
                    'allDay' => $block->is_all_day,
                    'color' => $block->is_all_day ? '#e5e7eb' : '#dc2626',
                    'backgroundColor' => $block->is_all_day ? '#e5e7eb' : '#dc2626',
                    'borderColor' => $block->is_all_day ? '#9ca3af' : '#991b1b',
                    'textColor' => $block->is_all_day ? '#374151' : '#ffffff',
                    'extendedProps' => [
                        'description' => 'Bloqueo de agenda',
                        'isBlock' => true,
                    ],
                    'url' => '', // Empty string to bypass plugin's url check
                ]
            );

        return array_merge($interviews->values()->all(), $blocks->values()->all());
    }

    public function resolveRecord(string|int $key): \Illuminate\Database\Eloquent\Model
    {
        // Si el ID comienza con 'block-', es un bloqueo de calendario
        if (is_string($key) && str_starts_with($key, 'block-')) {
            $blockId = str_replace('block-', '', $key);

            return \App\Models\CalendarBlock::findOrFail($blockId);
        }

        // De lo contrario, es una Interview
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
                    ? '🚫 Bloqueo de Agenda'
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
                                ->label('Razón del bloqueo')
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
                                ->visible(fn () => auth()->user()->can('delete', $record))
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
