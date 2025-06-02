<x-filament::widget>
    @php
        $data = $this->getData();
        $resourceName = $data['resourceName'] ?? null;
        $resourceUrl = null;
        if ($resourceName) {
            $resourceClass = "App\\Filament\\Resources\\{$resourceName}Resource";
            if (class_exists($resourceClass)) {
                $resourceUrl = $resourceClass::getUrl('index');
            }
        }
    @endphp
    @if($resourceUrl)
        <a href="{{ $resourceUrl }}" class="block group transition rounded-xl">
    @endif
    <div class="bg-white p-6 rounded-xl shadow transition-shadow group-hover:shadow-lg group-hover:shadow-primary-500/40">
        <div class="flex items-center space-x-4">
            <div class="flex-shrink-0">
                <x-filament::icon
                    :icon="$data['icon']"
                    class="w-10 h-10 text-primary"
                    style="color: #FF6100;"
                />
            </div>
            <div>
                <div class="text-2xl font-bold text-gray-800">{{ $data['value'] }}</div>
                <div class="text-md text-gray-600">{{ $data['label'] }}</div>
            </div>
        </div>
    </div>
    @if($resourceUrl)
        </a>
    @endif
</x-filament::widget>
