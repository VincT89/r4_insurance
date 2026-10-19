import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { fileURLToPath } from 'node:url';

export default defineConfig({
    resolve: {
        /** ExcelJS ships a prebuilt browser bundle; rebuild it so the patched transitive dependencies reach the browser too. */
        alias: [{ find: /^exceljs$/, replacement: fileURLToPath(new URL('./node_modules/.cache/r4-insurance/exceljs.cjs', import.meta.url)) }],
    },
    build: {
        chunkSizeWarningLimit: 550,
        rolldownOptions: {
            output: {
                codeSplitting: {
                    groups: [
                        {
                            name: 'icons',
                            test: /node_modules[\\/]lucide[\\/]/,
                        },
                    ],
                },
            },
        },
    },
    server: {
        host: '127.0.0.1',
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
