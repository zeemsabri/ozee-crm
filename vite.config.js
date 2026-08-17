import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: 'resources/js/app.js',
            refresh: true,
        }),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        // Scoped to .jsx/.tsx only so it never touches the existing Vue .js/.vue files.
        // Powers the React pages that live side-by-side with Vue during the page-by-page migration.
        react({
            include: '**/*.{jsx,tsx}',
        }),
    ],
    resolve: {
        alias: {
            '@': '/resources/js', // Or path.resolve(__dirname, 'resources/js')
        },
    },
});
