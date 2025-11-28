{{-- resources/views/filament/admin/logo.blade.php --}}
<div class="flex flex-col items-center justify-center w-full">
    {{-- Imagen arriba --}}
    <img src="{{ asset('images/recova-edificio-logo-body.png') }}" alt="Recova Rentals"
        class="h-16 w-auto mb-3 object-contain drop-shadow-[0_0_12px_rgba(6,182,212,0.6)]">

    {{-- Título principal --}}
    <span class="text-3xl font-bold text-transparent bg-clip-text bg-gradient-to-r
               from-cyan-400 via-purple-500 to-pink-500" style="text-shadow: 0 0 10px rgba(6, 182, 212, 0.5);">
        Recova Rentals
    </span>

    {{-- Subtítulo --}}
    <span class="text-sm text-gray-400 mt-1 tracking-wider uppercase">
        Panel de Administración
    </span>
</div>