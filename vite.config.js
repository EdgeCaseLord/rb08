import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/js/app.js',
                'resources/css/filament/admin/theme.css'
            ],
            refresh: true,
        }),
    ],
    // build: {
    //     outDir: 'public/build', // Matches your output
    //     manifest: true, // Generate manifest.json
    // },
    server: {
        cors: true,
    },
});
