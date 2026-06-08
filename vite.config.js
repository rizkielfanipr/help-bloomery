import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/app.css',
                'resources/js/app.js',
                'resources/css/filament/helpdesk/theme.css',
                'resources/css/filament/casual/theme.css',
                'resources/css/filament/driver/theme.css',
            ],
            refresh: true,
        }),
        tailwindcss(),
    ],
});
