import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import AutoImport from 'unplugin-auto-import/vite';
import Components from 'unplugin-vue-components/vite';
import { ElementPlusResolver } from 'unplugin-vue-components/resolvers';

export default defineConfig({
    build: {
        reportCompressedSize: false,
    },
    plugins: [
        vue(),
        laravel({
            input: ['resources/js/public.js', 'resources/js/admin.js', 'resources/js/register.js'],
            refresh: true,
        }),
        AutoImport({
            imports: ['vue'],
            resolvers: [ElementPlusResolver()],
            dts: false,
        }),
        Components({
            resolvers: [ElementPlusResolver({ importStyle: 'css' })],
            dts: false,
        }),
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
        origin: 'http://localhost:5173',
        cors: {
            origin: [
                'http://localhost:8084',
                'http://127.0.0.1:8084',
                'http://localhost',
                'http://127.0.0.1',
            ],
            credentials: true,
        },
        hmr: {
            host: 'localhost',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
