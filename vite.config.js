import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/profile.js', 'resources/js/docs.js', 'resources/js/explorer.js'],
            refresh: true,
        }),
        vue(),
        tailwindcss(),
    ],
    // VUE_DEBUG_HYDRATION=1 npm run build  -> the browser console names every difference between the pre-rendered HTML and the app
    define: process.env.VUE_DEBUG_HYDRATION ? { __VUE_PROD_HYDRATION_MISMATCH_DETAILS__: true } : {},
    server: {
        watch: { ignored: ['**/storage/framework/views/**'] },
    },
});
