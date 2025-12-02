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
                        <x-filament::icon icon="heroicon-o-calendar-days" class="w-4 h-4" />
                        <span>Interviews Este Mes</span>
                    </dt>
                    <dd class="text-sm font-semibold">
                        {{ $interviewsThisMonth }}
                    </dd>
                </div>

                <div class="flex items-center justify-between">
                    <dt class="flex items-center gap-2 text-sm text-gray-500">
                        <x-filament::icon icon="heroicon-o-x-circle" class="w-4 h-4" />
                        <span>Cancelaciones del Mes</span>
                    </dt>
                    <dd class="text-sm font-semibold text-red-500">
                        {{ $cancellationsThisMonth }}
                    </dd>
                </div>

                <div class="flex items-center justify-between">
                    <dt class="flex items-center gap-2 text-sm text-gray-500">
                        <x-filament::icon icon="heroicon-o-calendar" class="w-4 h-4" />
                        <span>Día Más Popular</span>
                    </dt>
                    <dd class="text-sm font-semibold text-primary-500">
                        {{ $mostPopularDay }}
                    </dd>
                </div>
            </dl>

            <div class="pt-4 border-t border-gray-200 dark:border-gray-700">
                <h3 class="mb-2 text-sm font-semibold">
                    Productos Más Populares
                </h3>

                <ul class="space-y-1 text-sm text-gray-500">
                    @foreach ($topProducts as $product)
                        <li class="flex items-center justify-between">
                            <span>{{ $product['name'] }}</span>
                            <span class="font-semibold">{{ $product['percentage'] }}%</span>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </x-filament::card>
</x-filament::widget>
