<div class="flex flex-row justify-between gap-x-6">
    <!-- Patients -->
    <div class="flex items-center space-x-4 px-8 py-4 mr-4 bg-white rounded-lg shadow w-full max-w-[200px]">
        <div class="flex-shrink-0">
            <x-filament::icon
                icon="{{ $patients['icon'] }}"
                class="w-10 h-10 text-primary mr-2"
                style="color: #FF6100;"
            />
        </div>
        <div class="flex-1">
            <div class="text-2xl font-bold">{{ $patients['value'] }}</div>
            <div class="text-sm text-gray-600">{{ $patients['label'] }}</div>
        </div>
    </div>

    <!-- Analyses -->
    <div class="flex items-center space-x-4 px-8 py-4 mr-4 bg-white rounded-lg shadow w-full max-w-[200px]">
        <div class="flex-shrink-0">
            <x-filament::icon
                icon="{{ $analyses['icon'] }}"
                class="w-10 h-10 text-primary mr-2"
                style="color: #FF6100;"
            />
        </div>
        <div class="flex-1">
            <div class="text-2xl font-bold">{{ $analyses['value'] }}</div>
            <div class="text-sm text-gray-600">{{ $analyses['label'] }}</div>
        </div>
    </div>

    <!-- Recipes -->
    <div class="flex items-center space-x-4 px-8 py-4 mr-4 bg-white rounded-lg shadow w-full max-w-[200px]">
        <div class="flex-shrink-0">
            <x-filament::icon
                icon="{{ $recipes['icon'] }}"
                class="w-10 h-10 text-primary mr-2"
                style="color: #FF6100;"
            />
        </div>
        <div class="flex-1">
            <div class="text-2xl font-bold">{{ $recipes['value'] }}</div>
            <div class="text-sm text-gray-600">{{ $recipes['label'] }}</div>
        </div>
    </div>

    <!-- Doctors -->
    <div class="flex items-center space-x-4 px-8 py-4 mr-4 bg-white rounded-lg shadow w-full max-w-[200px]">
        <div class="flex-shrink-0">
            <x-filament::icon
                icon="{{ $doctors['icon'] }}"
                class="w-10 h-10 text-primary mr-2"
                style="color: #FF6100;"
            />
        </div>
        <div class="flex-1">
            <div class="text-2xl font-bold">{{ $doctors['value'] }}</div>
            <div class="text-sm text-gray-600">{{ $doctors['label'] }}</div>
        </div>
    </div>
</div>
