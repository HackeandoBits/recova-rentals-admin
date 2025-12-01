{{-- resources/views/filament/pages/dashboard.blade.php --}}

<x-filament-panels::page class="fi-dashboard-page">
    {{-- Anulamos el header que genera Filament por defecto --}}
    <x-slot name="header"></x-slot>

    <div class="max-w-7xl mx-auto space-y-6">
        {{-- Header personalizado que sí podés tocar a gusto --}}
        <header class="space-y-1">
            <h1 class="text-2xl sm:text-3xl font-bold tracking-tight text-white">
                Panel de Control
            </h1>
            <p class="text-gray-400">
                ¡Bienvenido! Aquí tienes un resumen de tu negocio de alquiler.
            </p>
        </header>

        {{-- Widgets del dashboard --}}
        <x-filament-widgets::widgets :columns="$this->getColumns()" :data="[
            ...property_exists($this, 'filters') ? ['filters' => $this->filters] : [],
            ...$this->getWidgetData(),
        ]" :widgets="$this->getVisibleWidgets()" />
    </div>
</x-filament-panels::page>
