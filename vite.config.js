import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { readFileSync, readdirSync } from 'node:fs';

const pdfPackage = new URL('./node_modules/pdfjs-dist/', import.meta.url);

const pdfPrintAssets = {
    name: 'workdesk-pdf-print-assets',
    apply: 'build',
    generateBundle() {
        for (const directory of ['cmaps', 'iccs', 'standard_fonts', 'wasm']) {
            const folder = new URL(`${directory}/`, pdfPackage);
            for (const file of readdirSync(folder, { withFileTypes: true })) {
                if (!file.isFile() || /quickjs/i.test(file.name)) continue;
                this.emitFile({
                    type: 'asset',
                    fileName: `assets/pdfjs/${directory}/${file.name}`,
                    source: readFileSync(new URL(file.name, folder)),
                });
            }
        }
    },
};

export default defineConfig({
    plugins: [
        tailwindcss(),
        pdfPrintAssets,
        laravel({
            input: ['resources/css/app.css', 'resources/css/scm.css', 'resources/css/scm-cbe.css', 'resources/css/vendor-portal.css', 'resources/js/app.js', 'resources/js/global-chat.js', 'resources/js/vendor-portal.js', 'resources/js/document-print.js'],
            refresh: false,
        }),
    ],
});
