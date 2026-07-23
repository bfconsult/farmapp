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
    server: {
        // Without this, Vite's default "localhost" resolved to IPv6-only
        // (::1) on this machine, while the app itself is reached via IPv4 -
        // that mismatch caused intermittent "Failed to fetch dynamically
        // imported module" errors when the browser's own localhost
        // resolution didn't match whichever address Vite happened to bind.
        host: '127.0.0.1',
    },
});
