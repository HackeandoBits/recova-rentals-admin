<x-filament-forms::field-wrapper :field="$field">
    <div x-data="{
        state: $wire.$entangle('{{ $getStatePath() }}'),
        picker: null,
        blockedDates: @js($blockedDates ?? []),
        init() {
            // Esperar a que Flatpickr esté cargado globalmente
            let attempts = 0;
            const initFlatpickr = () => {
                if (window.flatpickr) {
                    this.configureAndMount();
                } else if (attempts < 10) {
                    attempts++;
                    setTimeout(initFlatpickr, 100);
                } else {
                    console.error('Flatpickr no se pudo cargar.');
                }
            };
            
            initFlatpickr();
        },
        configureAndMount() {
             let config = {
                locale: window.flatpickrSpanish || 'es', 
                dateFormat: 'Y-m-d',
                minDate: 'today',
                maxDate: new Date().fp_incr(365),
                disable: this.blockedDates,
                defaultDate: this.state,
                onChange: (selectedDates, dateStr) => {
                    this.state = dateStr;
                },
                onDayCreate: (dObj, dStr, fp, dayElem) => {
                    const dateStr = fp.formatDate(dayElem.dateObj, 'Y-m-d');
                    if (this.blockedDates.includes(dateStr)) {
                        dayElem.classList.add('admin-blocked');
                    }
                }
            };

            this.picker = window.flatpickr(this.$refs.input, config);

            $watch('state', value => {
                if (value && this.picker) {
                    this.picker.setDate(value, false);
                }
            });

            $watch('blockedDates', value => {
                if (this.picker) {
                    this.picker.set('disable', value);
                    this.picker.redraw();
                }
            });
        }
    }">
        <!-- Styling to match Filament inputs -->
        <input 
            x-ref="input"
            type="text" 
            x-model="state"
            placeholder="Seleccionar fecha..."
            class="block w-full transition duration-75 rounded-lg shadow-sm focus:ring-1 focus:ring-inset focus:ring-primary-600 disabled:opacity-70 disabled:cursor-not-allowed border-gray-300 focus:border-primary-600 bg-white text-gray-950 dark:bg-white/5 dark:text-white dark:border-white/10 dark:focus:border-primary-500"
            {{ $applyStateBindingModifiers('wire:model') }}="{{ $getStatePath() }}"
        />
    </div>
</x-filament-forms::field-wrapper>
