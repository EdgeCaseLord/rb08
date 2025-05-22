<x-filament::widget>
    <x-filament::card>
    <div class="flex items-center space-x-4">
            <div class="flex-shrink-0">
                <x-filament::icon
                    :icon="$this->getData()['icon']"
                    class="w-10 h-10 text-primary"
                    style="color: #FF6100;"
                />
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-800">{{ $this->getData()['value'] }}</div>
                <div class="text-sm text-gray-600">{{ $this->getData()['label'] }}</div>
            </div>
        </div>
    </x-filament::card>
</x-filament::widget>
