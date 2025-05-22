<x-filament::widget>
    <x-filament::card>
        <h2 class="text-lg font-semibold mb-4">Quick Links</h2>
        <ul class="space-y-2">
            @foreach ($handbuchLinks as $link)
                <li>
                    <a href="{{ $link['url'] }}" class="text-orange-600 hover:underline">{{ $link['title'] }}</a>
                </li>
            @endforeach
        </ul>
    </x-filament::card>
</x-filament::widget>
