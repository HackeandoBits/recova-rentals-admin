{{-- resources/views/filament/pages/profile.blade.php --}}
<x-filament-panels::page class="fi-profile-page">
    <form wire:submit="submit" class="space-y-6">
        {{ $this->form }}
    </form>
</x-filament-panels::page>
