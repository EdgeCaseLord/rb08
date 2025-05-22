<!-- resources/views/components/dashboard-grid.blade.php -->
<div class="dashboard-grid">
    {{ $slot }}
</div>

<style>
    .dashboard-grid {
        display: grid;
        grid-template-columns: repeat(4, 1fr);
        grid-template-rows: 150px 300px 300px;
        gap: 1.5rem;
        min-height: 750px;
    }

    .dashboard-grid .dashboard-stats-widget {
        grid-column: 1 / 5;
        grid-row: 1 / 2;
    }

    .dashboard-grid .latest-analyses-table-widget {
        grid-column: 1 / 4;
        grid-row: 2 / 4;
        max-height: 600px;
        overflow-y: auto;
    }

    .dashboard-grid .handbuch-widget {
        grid-column: 4 / 5;
        grid-row: 2 / 3;
        max-height: 300px;
    }

    .dashboard-grid .links-widget {
        grid-column: 4 / 5;
        grid-row: 3 / 4;
        max-height: 300px;
    }

    .dashboard-grid > div {
        height: 100%;
        width: 100%;
    }
</style>
