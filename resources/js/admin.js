import axios from 'axios';
import AdminApp from './apps/admin/AdminApp.vue';
import { AUTH_TOKEN_STORAGE_KEY, setAxiosAuthToken } from './composables/useAuthSession';
import { SPA_APP_SELECTOR, createSpaApp, hasSpaMountTarget } from './createSpaApp';

async function bootstrap() {
    if (!hasSpaMountTarget()) {
        return;
    }

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

    const app = createSpaApp(AdminApp);

    app.mount(SPA_APP_SELECTOR);
}

bootstrap();
