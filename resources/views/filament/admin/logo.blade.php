{{-- resources/views/filament/admin/logo.blade.php --}}
@php
    // En Filament v3 el login del panel "admin" suele ser este nombre de ruta:
    $isLogin = request()->routeIs('filament.admin.auth.login');
@endphp

<div class="rr-logo-wrapper flex items-center gap-2">
    {{-- Imagen: grande en login, chica en el resto --}}
    <img src="{{ asset($isLogin ? 'images/recova-edificio-logo-body.png' : 'images/rentalsblanco-1.png') }}"
        alt="Recova Rentals" class="rr-logo-icon object-contain drop-shadow-[0_0_12px_rgba(6,182,212,0.6)]">

    {{-- Textos --}}
    <div class="rr-logo-text flex flex-col leading-tight">
        <span
            class="rr-logo-title font-bold text-transparent bg-clip-text bg-gradient-to-r
                   from-cyan-400 via-purple-500 to-pink-500"
            style="text-shadow: 0 0 10px rgba(6, 182, 212, 0.5);">
            Recova Rentals
        </span>

        @if ($isLogin)
            <span class="rr-logo-subtitle text-[0.7rem] text-gray-400 tracking-wider uppercase">
                Panel de Administración
            </span>
        @endif
    </div>
</div>
