import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            // Un seul point d'entrée CSS (thème Bootstrap surchargé) et un seul
            // point d'entrée JS, qui monte les îlots React là où Blade les déclare.
            input: ['resources/scss/app.scss', 'resources/js/app.js'],
            refresh: ['resources/views/**', 'routes/**'],
        }),
        react(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 utilise encore @import et les fonctions de
                // couleur historiques de Sass : on tait ces avertissements tant
                // que Bootstrap ne migre pas vers @use.
                silenceDeprecations: ['import', 'global-builtin', 'color-functions'],
                quietDeps: true,
            },
        },
    },
    resolve: {
        alias: {
            '@': '/resources/js',
        },
    },
});
