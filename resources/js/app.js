import './bootstrap';
import '../css/spa.css';
import { createApp } from 'vue';
import AdminApp from './apps/admin/AdminApp.vue';
import PublicApp from './apps/public/PublicApp.vue';
import RegisterApp from './apps/common/RegisterApp.vue';
import { createAppI18n } from './i18n';
import axios from 'axios';
import { AUTH_TOKEN_STORAGE_KEY, setAxiosAuthToken } from './composables/useAuthSession';

async function bootstrap() {
    if (!document.querySelector('#app')) {
        return;
    }

    const isAdminArea = window.location.pathname.startsWith('/admin');
    const isRegisterArea = window.location.pathname.startsWith('/register');

    if (isAdminArea) {
        const token = window.localStorage.getItem(AUTH_TOKEN_STORAGE_KEY);

        if (token) {
            try {
                setAxiosAuthToken(token);

                const response = await axios.get('/api/auth/me', {
                    headers: {
                        Authorization: `Bearer ${token}`,
                    },
                });

                if (!response?.data?.is_staff) {
                    window.location.replace('/');
                    return;
                }
            } catch (_error) {
                window.localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY);
                setAxiosAuthToken('');
            }
        }
    }

    const targetApp = isRegisterArea ? RegisterApp : isAdminArea ? AdminApp : PublicApp;

    const app = createApp(targetApp);

    app.use(createAppI18n());
    app.mount('#app');
}

bootstrap();
