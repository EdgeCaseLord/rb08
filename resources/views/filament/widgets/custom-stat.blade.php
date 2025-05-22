<div class="flex items-center space-x-4">
    <!-- Left Column: Icon -->
    <div class="flex-shrink-0">
        <x-filament::icon
            :icon="$icon"
            class="w-8 h-8 text-gray-500"
        />
    </div>

    <!-- Right Column: Number and Label -->
    <div class="flex-1">
        <div class="text-2xl font-bold">{{ $value }}</div>
        <div class="text-sm text-gray-600">{{ $label }}</div>
    </div>
</div>
