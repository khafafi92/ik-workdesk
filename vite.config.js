import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        tailwindcss(),
        laravel({
            input: ['resources/css/app.css', 'resources/css/scm.css', 'resources/css/scm-cbe.css', 'resources/css/vendor-portal.css', 'resources/js/app.js', 'resources/js/global-chat.js', 'resources/js/vendor-portal.js'],
            refresh: false,
        }),
    ],
});
