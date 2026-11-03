import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { google } from 'laravel-vite-plugin/fonts';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/css/shop.css',
                'resources/css/admin.css',
                'resources/js/app.js',
            ],
            refresh: true,
            // Polices téléchargées à la compilation et servies depuis public/build :
            // aucune requête vers Google depuis le navigateur des clients (Loi 25).
            fonts: [
                google('Bricolage Grotesque', {
                    alias: 'titre',
                    variable: '--font-titre',
                    weights: [600, 800],
                    subsets: ['latin', 'latin-ext'],
                    fallbacks: ['system-ui', 'sans-serif'],
                }),
                google('Manrope', {
                    alias: 'corps',
                    variable: '--font-corps',
                    weights: [400, 500, 700, 800],
                    subsets: ['latin', 'latin-ext'],
                    fallbacks: ['system-ui', 'sans-serif'],
                }),
            ],
        }),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
