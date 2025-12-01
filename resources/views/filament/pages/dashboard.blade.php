{{-- resources/views/filament/pages/dashboard.blade.php --}}

<x-filament-panels::page class="fi-dashboard-page">
    {{-- Anulamos el header que genera Filament por defecto --}}
    <x-slot name="header"></x-slot>

    <div class="mx-auto w-full space-y-6">
        {{-- Widgets --}}
        <x-filament-widgets::widgets :columns="$this->getColumns()" :data="[
            ...property_exists($this, 'filters') ? ['filters' => $this->filters] : [],
            ...$this->getWidgetData(),
        ]" :widgets="$this->getVisibleWidgets()" />
    </div>
</x-filament-panels::page>
