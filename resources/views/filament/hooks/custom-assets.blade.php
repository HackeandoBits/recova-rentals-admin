<link rel="stylesheet" href="{{ asset('css/recova-dashboard.css') }}?v={{ time() }}">
@vite(['resources/js/app.js'])

<script>
    // Force Dark Mode & Persist
    if (localStorage.theme !== 'dark') {
        localStorage.theme = 'dark';
        document.documentElement.classList.add('dark');
    }
    // Observer to prevent changes
    new MutationObserver(() => {
        if (!document.documentElement.classList.contains('dark')) {
            document.documentElement.classList.add('dark');
        }
    }).observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
</script>

<style>
    /* Ocultar el switcher de tema (Luna/Sol) en el menú de usuario y topbar */
    .fi-theme-switcher,
    [data-filament-panel-theme-switcher],
    button[aria-label*="Switch to"], 
    button[aria-label*="Cambiar a modo"] {
        display: none !important;
    }
</style>
