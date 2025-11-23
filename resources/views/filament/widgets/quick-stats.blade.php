<x-filament::widget>
    <x-filament::card>
        <div class="space-y-4">
            <div>
                <h2 class="text-lg font-semibold">
                    Estadísticas Rápidas
                </h2>
                <p class="text-sm text-gray-400">
                    Resumen general de tu operación.
                </p>
            </div>

            <dl class="space-y-3">
                <div class="flex items-center justify-between">
                    <dt class="flex items-center gap-2 text-sm text-gray-500">
                        <x-filament::icon icon="heroicon-o-user-group" class="w-4 h-4" />
                        <span>Total Clientes</span>
                    </dt>
                    <dd class="text-sm font-semibold">
                        {{ $totalClients }}
                    </dd>
                </div>

                <div class="flex items-center justify-between">
                    <dt class="flex items-center gap-2 text-sm text-gray-500">
                        <x-filament::icon icon="heroicon-o-cube" class="w-4 h-4" />
                        <span>Tipos de Equipos</span>
                    </dt>
                    <dd class="text-sm font-semibold">
                        {{ $equipmentTypes }}
                    </dd>
                </div>

                <div class="flex items-center justify-between">
                    <dt class="flex items-center gap-2 text-sm text-gray-500">
                        <x-filament::icon icon="heroicon-o-check-badge" class="w-4 h-4" />
                        <span>Tasa de Éxito</span>
                    </dt>
                    <dd class="text-sm font-semibold text-emerald-500">
                        {{ $successRate }}%
                    </dd>
                </div>
            </dl>

            <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                <h3 class="mb-2 text-sm font-semibold">
                    Equipos Populares
                </h3>

                <ul class="space-y-1 text-sm text-gray-500">
                    @foreach ($popularEquipment as $item)
                        <li class="flex items-center justify-between">
                            <span>{{ $item['name'] }}</span>
                            <span class="font-semibold">{{ $item['percentage'] }}%</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </x-filament::card>
</x-filament::widget>