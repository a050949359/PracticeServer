import axios from 'axios';

export const AUTH_TOKEN_STORAGE_KEY = 'practice_auth_token';

export function setAxiosAuthToken(token) {
    if (token) {
        axios.defaults.headers.common.Authorization = `Bearer ${token}`;
        return;
    }

    delete axios.defaults.headers.common.Authorization;
}

export function useAuthSession() {
    const authToken = ref('');
    const currentUser = ref(null);

    const isAuthenticated = computed(() => {
        return Boolean(authToken.value && currentUser.value);
    });

    const isStaff = computed(() => {
        return Boolean(currentUser.value?.is_staff);
    });

    const isEmailVerified = computed(() => {
        return Boolean(currentUser.value?.email_verified_at);
    });

    const userLabel = computed(() => {
        return currentUser.value?.name ?? currentUser.value?.email ?? '會員';
    });

    const buildAuthHeaders = () => {
        if (!authToken.value) {
            return {};
        }

        return {
            Authorization: `Bearer ${authToken.value}`,
        };
    };

    const setAuthToken = (token) => {
        authToken.value = token ?? '';

        if (authToken.value) {
            localStorage.setItem(AUTH_TOKEN_STORAGE_KEY, authToken.value);
            setAxiosAuthToken(authToken.value);
            return;
        }

        localStorage.removeItem(AUTH_TOKEN_STORAGE_KEY);
        setAxiosAuthToken('');
    };

    const loadCurrentUser = async () => {
        if (!authToken.value) {
            currentUser.value = null;
            return null;
        }

        try {
            const response = await axios.get('/api/auth/me', {
                headers: buildAuthHeaders(),
            });

            currentUser.value = response.data;
            return currentUser.value;
        } catch (_error) {
            currentUser.value = null;
            setAuthToken('');
            return null;
        }
    };

    const applyLoginToken = async (token) => {
        setAuthToken(token);
        await loadCurrentUser();
    };

    const updateProfile = async (payload) => {
        const response = await axios.patch('/api/auth/me', payload, {
            headers: buildAuthHeaders(),
        });

        currentUser.value = response.data.user;

        return currentUser.value;
    };

    const changePassword = async (payload) => {
        await axios.post('/api/auth/password/change', payload, {
            headers: buildAuthHeaders(),
        });
    };

    const logout = async () => {
        try {
            await axios.post(
                '/api/auth/logout',
                {},
                {
                    headers: buildAuthHeaders(),
                }
            );
        } catch (_error) {
            // Ignore logout API errors and clear local state anyway.
        } finally {
            currentUser.value = null;
            setAuthToken('');
        }
    };

    const restoreSession = async () => {
        const token = localStorage.getItem(AUTH_TOKEN_STORAGE_KEY);

        if (!token) {
            return;
        }

        setAuthToken(token);
        await loadCurrentUser();
    };

    return {
        currentUser,
        isAuthenticated,
        isStaff,
        isEmailVerified,
        userLabel,
        buildAuthHeaders,
        applyLoginToken,
        updateProfile,
        changePassword,
        loadCurrentUser,
        logout,
        restoreSession,
    };
}
