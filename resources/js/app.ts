import App from '@/App.vue';
import { configureEcho } from '@laravel/echo-vue';
import { createPinia } from 'pinia';
import { createApp } from 'vue';
import router from './router';

configureEcho({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 80),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 443),
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});

const app = createApp(App);

app.config.errorHandler = (error, instance, info) => {
    console.error('Vue component error:', { error, instance, info });
};

app.use(createPinia());
app.use(router);

app.mount('#app');
