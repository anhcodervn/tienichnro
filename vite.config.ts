import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import path from 'path';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const appUrl = env.APP_URL ? new URL(env.APP_URL) : null;

    return {
        plugins: [
            laravel({
                input: [
                    'resources/css/app.css',
                    'resources/js/app.ts',
                    'resources/css/client.css',
                    'resources/js/client.js',
                    'resources/js/client-support.js',
                ],
                refresh: true,
            }),
            vue(),
        ],
        server: {
            host: '0.0.0.0',
            port: 5173,
            cors: true,
            hmr: appUrl
                ? {
                      host: appUrl.hostname,
                      protocol: appUrl.protocol === 'https:' ? 'wss' : 'ws',
                      port: 5173,
                  }
                : undefined,
            watch: {
                ignored: ['**/storage/framework/views/**'],
            },
        },
        resolve: {
            alias: {
                '@': path.resolve(__dirname, 'resources/js'),
            },
        },
    };
});
