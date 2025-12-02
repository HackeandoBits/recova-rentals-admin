<x-filament-panels::page class="fi-calendar-page">
    <x-slot name="header"></x-slot>

    <div class="space-y-6">
        @livewire(\App\Filament\Widgets\CalendarWidget::class)
        <style>
            .fc-event {
                cursor: pointer !important;
            }

            .fc-event:hover {
                opacity: 0.9;
                transform: scale(1.02);
                transition: all 0.2s ease;
            }
        </style>
    </div>
</x-filament-panels::page>
