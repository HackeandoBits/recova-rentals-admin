<x-filament-panels::page>
    {{-- Filtro de Rango de Fechas --}}
    <div class="mb-6">
        <x-filament::card>
            <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
                <div class="flex-1">
                    <h3 class="text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Filtrar por Período
                    </h3>
                    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                Desde
                            </label>
                            <input type="date" wire:model.live="dateRange.from"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                        </div>
                        <div>
                            <label class="text-xs font-medium text-gray-500 dark:text-gray-400">
                                Hasta
                            </label>
                            <input type="date" wire:model.live="dateRange.to"
                                class="mt-1 block w-full rounded-lg border-gray-300 shadow-sm focus:border-primary-500 focus:ring-primary-500 dark:border-gray-600 dark:bg-gray-700 dark:text-white" />
                        </div>
                    </div>
                </div>

                <div class="flex gap-2">
                    <button type="button"
                        wire:click="$set('dateRange', {
                            from: '{{ now()->startOfMonth()->toDateString() }}',
                            to: '{{ now()->endOfMonth()->toDateString() }}'
                        })"
                        class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                        <x-filament::icon icon="heroicon-o-calendar" class="w-4 h-4" />
                        Este Mes
                    </button>

                    <button type="button"
                        wire:click="$set('dateRange', {
                            from: '{{ now()->subDays(30)->toDateString() }}',
                            to: '{{ now()->toDateString() }}'
                        })"
                        class="inline-flex items-center gap-1 px-3 py-2 text-sm font-medium text-gray-700 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 dark:bg-gray-800 dark:text-gray-300 dark:border-gray-600 dark:hover:bg-gray-700">
                        <x-filament::icon icon="heroicon-o-calendar-days" class="w-4 h-4" />
                        Últimos 30 Días
                    </button>
                </div>
            </div>
        </x-filament::card>
    </div>

    {{-- Widgets de Estadísticas --}}
    <div class="mb-6">
        @livewire($this->getStatsWidget(), ['dateRange' => $dateRange])
    </div>

    {{-- Gráficos --}}
    <div class="grid grid-cols-1 gap-6 mb-6 md:grid-cols-2">
        @foreach ($this->getChartWidgets() as $widget)
            <div>
                @livewire($widget, ['dateRange' => $dateRange])
            </div>
        @endforeach
    </div>

    {{-- Tabla de Historial --}}
    <div>
        @livewire($this->getTableWidget())
    </div>
</x-filament-panels::page>
