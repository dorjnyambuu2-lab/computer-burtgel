import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
    build: {
        rollupOptions: {
            output: {
                // «-v2»: Render дээр давхар gzip-ээр эвдэрч ирсэн CSS, JS-ийг хөтчүүд 1 жилээр (immutable) кэшэлсэн тул
                // файлын нэрийг өөрчилж шинээр татуулна. Дахин ийм хэрэг гарвал дугаарыг нэмнэ.
                entryFileNames: 'assets/[name]-[hash]-v2.js',
                chunkFileNames: 'assets/[name]-[hash]-v2.js',
                assetFileNames: 'assets/[name]-[hash]-v2[extname]',
            },
        },
    },
});