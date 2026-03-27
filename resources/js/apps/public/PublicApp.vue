<template>
    <main class="spa-root">
        <app-navbar
            brand-href="/"
            brand-label="PracticeServer"
            :nav-label="t('navbar.aria.publicNavigation')"
            :left-nav-label="t('navbar.aria.vertexNavigation')"
            :dropdown-menus="navbarDropdownMenus"
            :actions="navbarActions"
            :authenticated="isAuthenticated"
            :user-status-label="userStatusLabel"
            :user-label="userLabel"
            :menu-items="authMenuItems"
            :current-path="route.path"
            :navigate="navigate"
            @action="handleNavbarAction"
        />

        <section class="spa-wrap">
            <div class="spa-content">
                <div class="spa-page-head">
                    <p class="spa-page-breadcrumb">{{ pageBreadcrumb }}</p>
                    <h1 class="spa-page-title">{{ pageTitle }}</h1>
                </div>

                <router-view v-slot="{ Component }">
                    <component
                        :is="Component"
                        v-bind="currentPageProps"
                    />
                </router-view>
            </div>
        </section>
    </main>

    <auth-dialogs
        v-model:login-visible="loginDialogVisible"
        v-model:register-visible="registerDialogVisible"
        login-title="一般使用者登入"
        login-audience="public"
        register-title="一般使用者註冊"
        register-context="user_self_register"
        @logged-in="handleLoggedIn"
    />

    <profile-dialog
        v-model:visible="profileDialogVisible"
        :user="currentUser"
        @submit="submitProfile"
        @change-password="submitChangePassword"
    />

    <invite-dialog
        v-model:visible="inviteDialogVisible"
        default-context="user_invited_register"
        :allowed-contexts="['user_invited_register']"
        :auth-headers="buildAuthHeaders()"
    />
</template>

<script setup>
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';
import { useRoute, useRouter } from 'vue-router';
import AppNavbar from '../../components/AppNavbar.vue';
import AuthDialogs from '../../components/AuthDialogs.vue';
import InviteDialog from '../../components/InviteDialog.vue';
import ProfileDialog from '../../components/ProfileDialog.vue';
import {
    AUTH_MENU_ITEM_KEYS,
    GUEST_NAVBAR_ACTION_KEYS,
    buildAuthMenuItems,
    buildGuestNavbarActions,
    buildNavbarDropdownMenus,
} from './navbarConfig';
import { useAuthSession } from '../../composables/useAuthSession';

const { t } = useI18n();
const route = useRoute();
const router = useRouter();

const {
    currentUser,
    isAuthenticated,
    isStaff,
    isEmailVerified,
    userLabel,
    buildAuthHeaders,
    applyLoginToken,
    updateProfile,
    changePassword,
    logout,
    restoreSession,
    loadCurrentUser,
} =
    useAuthSession();

const navigate = (target) => {
    router.push(target);
};

const guestNavbarActions = computed(() => {
    return buildGuestNavbarActions(t);
});

const authMenuItems = computed(() => {
    return buildAuthMenuItems(t);
});

const navbarDropdownMenus = computed(() => {
    return buildNavbarDropdownMenus(t);
});

const pageTitle = computed(() => {
    const titleKey = route.meta?.titleKey;

    return typeof titleKey === 'string' ? t(titleKey) : 'PracticeServer';
});

const pageBreadcrumb = computed(() => {
    const breadcrumbKeys = Array.isArray(route.meta?.breadcrumbKeys) ? route.meta.breadcrumbKeys : [];

    return breadcrumbKeys.map((key) => t(key)).join(' / ');
});

const navbarActions = computed(() => {
    return isAuthenticated.value ? [] : guestNavbarActions.value;
});

const userStatusLabel = computed(() => {
    if (!isAuthenticated.value || isEmailVerified.value) {
        return '';
    }

    return t('navbar.userStatus.unverified');
});

const loginDialogVisible = ref(false);
const registerDialogVisible = ref(false);
const profileDialogVisible = ref(false);
const inviteDialogVisible = ref(false);

const currentPageProps = computed(() => {
    if (route.name !== 'public.home') {
        return {};
    }

    return {
        isAuthenticated: isAuthenticated.value,
        openLoginDialog,
        openRegisterDialog,
        openProfileDialog,
        openInviteDialog,
    };
});

const openLoginDialog = () => {
    loginDialogVisible.value = true;
};

const openRegisterDialog = () => {
    registerDialogVisible.value = true;
};

const openProfileDialog = async () => {
    await loadCurrentUser();
    profileDialogVisible.value = true;
};

const openInviteDialog = () => {
    inviteDialogVisible.value = true;
};

const submitProfile = async (payload) => {
    try {
        await updateProfile(payload);
        profileDialogVisible.value = false;
        ElMessage.success(t('profileDialog.messages.updateSuccess'));
    } catch (_error) {
        ElMessage.error(t('profileDialog.messages.updateFailure'));
    }
};

const submitChangePassword = async (payload) => {
    try {
        await changePassword(payload);
        ElMessage.success(t('profileDialog.messages.passwordUpdateSuccess'));
    } catch (error) {
        const code = error?.response?.data?.code;
        const messageByCode = {
            current_password_incorrect: t('profileDialog.messages.currentPasswordIncorrect'),
            password_change_cooldown: t('profileDialog.messages.passwordCooldown'),
            password_history_violation: t('profileDialog.messages.passwordHistoryViolation'),
            password_reused: t('profileDialog.messages.passwordReused'),
        };

        ElMessage.error(messageByCode[code] ?? t('profileDialog.messages.passwordUpdateFailure'));
    }
};

const handleNavbarAction = (actionKey) => {
    if (actionKey === GUEST_NAVBAR_ACTION_KEYS.login) {
        openLoginDialog();
    }

    if (actionKey === GUEST_NAVBAR_ACTION_KEYS.register) {
        openRegisterDialog();
    }

    if (actionKey === AUTH_MENU_ITEM_KEYS.profile) {
        openProfileDialog();
    }

    if (actionKey === AUTH_MENU_ITEM_KEYS.invite) {
        openInviteDialog();
    }

    if (actionKey === AUTH_MENU_ITEM_KEYS.logout) {
        submitLogout();
    }
};

const enforcePublicOnlyAccount = async () => {
    if (!isStaff.value) {
        return false;
    }

    await logout();
    ElMessage.error('後台帳號不可登入前台，已自動登出');

    return true;
};

const handleLoggedIn = async (token) => {
    await applyLoginToken(token);

    await enforcePublicOnlyAccount();
};

const submitLogout = async () => {
    await logout();
    ElMessage.success('已登出');
};

onMounted(async () => {
    await restoreSession();

    await enforcePublicOnlyAccount();
});

watchEffect(() => {
    document.title = `${pageTitle.value} | PracticeServer`;
});
</script>
