<x-filament-panels::page>
    <x-filament-panels::form wire:submit="submit">
        {{ $this->form }}

        <x-filament-panels::form.actions :actions="$this->getFormActions()" />
    </x-filament-panels::form>

    <!-- JavaScript to handle language changes -->
    <script>
        document.addEventListener('livewire:init', () => {
            Livewire.on('language-changed', (event) => {
                console.log('Language changed event received:', event.language);
                window.location.reload();
            });
        });
    </script>
</x-filament-panels::page>
