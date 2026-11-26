import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.jsx',
            refresh: true,
        }),
        react(),
    ],
    build: {
        rollupOptions: {
            output: {
                // PixiJS (the farm view) in a chunk of its own: its lazily
                // loaded renderers import its core back, and without this
                // Rollup folds the FarmView page into that shared chunk,
                // leaving the page out of the manifest Laravel loads it by.
                // Vite's own loader helper gets a chunk too, or it lands in
                // PixiJS's and every page would load PixiJS to get it.
                manualChunks: (id) => {
                    if (id.includes('vite/preload-helper') || id.includes('commonjsHelpers')) return 'vite-runtime';
                    if (id.includes('node_modules/pixi.js')) return 'pixi';
                    return undefined;
                },
            },
        },
    },
    server: {
        // Without this, Vite's default "localhost" resolved to IPv6-only
        // (::1) on this machine, while the app itself is reached via IPv4 -
        // that mismatch caused intermittent "Failed to fetch dynamically
        // imported module" errors when the browser's own localhost
        // resolution didn't match whichever address Vite happened to bind.
        host: '127.0.0.1',
    },
});
