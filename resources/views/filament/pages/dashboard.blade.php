<x-filament-panels::page>
    <div class="filament-dashboard">
        <!-- Row 1: Stat Cards -->
        <div class="col-span-4 row-start-1">
            <livewire:filament.widgets.stats-overview-widget />
        </div>
        <!-- Rows 2-3: Table -->
        <div class="col-span-3 row-span-2 row-start-2">
            <livewire:filament.widgets.latest-analyses-table />
        </div>
        <!-- Rows 2-3: Handbuch and Links -->
        <div class="col-span-1 row-span-1 row-start-2">
            <livewire:filament.widgets.handbuch-widget />
        </div>
        <!-- <div class="col-span-1 row-span-1 row-start-3">
            <livewire:filament.widgets.links-widget />
        </div> -->
    </div>
</x-filament-panels::page>
