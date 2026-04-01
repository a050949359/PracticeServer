import axios from 'axios';
import AdminApp from './apps/admin/AdminApp.vue';
import { adminRouter } from './apps/admin/router';
import { AUTH_TOKEN_STORAGE_KEY, setAxiosAuthToken } from './composables/useAuthSession';
import { SPA_APP_SELECTOR, createSpaApp, hasSpaMountTarget } from './createSpaApp';

const ADMIN_HOME_PATH = '/admin';

function shouldProtectAdminRoute(pathname) {
    return pathname !== ADMIN_HOME_PATH && pathname.startsWith('/admin');
}

async function bootstrap() {
    if (!hasSpaMountTarget()) {
        return;
    }

    const isProtectedRoute = shouldProtectAdminRoute(window.location.pathname);
    const token = window.localStorage.getItem(AUTH_TOKEN_STORAGE_KEY);

    if (!token && isProtectedRoute) {
        window.location.replace(ADMIN_HOME_PATH);
        return;
    }

    if (token) {
        try {
            setAxiosAuthToken(token);

            const response = await axios.get('/api/auth/me', {
                headers: {
                    Authorization: `Bearer ${token}`,
                },
            });

            if (!response?.data?.is_staff) {
                window.localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY);
                setAxiosAuthToken('');

                if (isProtectedRoute) {
                    window.location.replace(ADMIN_HOME_PATH);
                    return;
                }
            }
        } catch (_error) {
            window.localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY);
            setAxiosAuthToken('');

            if (isProtectedRoute) {
                window.location.replace(ADMIN_HOME_PATH);
                return;
            }
        }
    }

    const app = createSpaApp(AdminApp);

    app.use(adminRouter);

    await adminRouter.isReady();

    app.mount(SPA_APP_SELECTOR);
}

bootstrap();
