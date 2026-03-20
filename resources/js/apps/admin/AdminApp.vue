<template>
    <main class="spa-root">
        <app-navbar
            brand-href="/admin"
            brand-label="PracticeServer Admin"
            nav-label="auth actions"
            :actions="navbarActions"
            :authenticated="isAuthenticated"
            :user-status-label="userStatusLabel"
            :user-label="userLabel"
            :menu-items="authMenuItems"
            @action="handleNavbarAction"
        />

        <section class="spa-wrap">
            <div class="spa-content">
                <el-card class="spa-panel" shadow="hover">
                    <template #header>
                        <div class="spa-panel-header">
                            <el-tag effect="dark" type="info">Admin Area</el-tag>
                            <span class="spa-panel-title">Element Plus 已接上</span>
                        </div>
                    </template>

                    <h1 class="spa-title">這是管理後台 SPA</h1>
                    <p class="spa-description">
                        目前既有功能先歸在 admin 區，登入與註冊以中間彈窗方式操作。
                    </p>

                    <el-space class="spa-actions-row" wrap>
                        <el-button v-if="!isAuthenticated" plain @click="openLoginDialog">立即登入</el-button>
                        <el-button v-if="!isAuthenticated" type="primary" @click="openRegisterDialog"
                            >立即註冊</el-button
                        >
                        <el-button v-if="isAuthenticated" plain @click="openProfileDialog">個人資料</el-button>
                        <el-button v-if="isAuthenticated" type="primary" @click="openInviteDialog">發送邀請</el-button>
                        <el-button plain>查看更多</el-button>
                    </el-space>
                </el-card>
            </div>
        </section>
    </main>

    <auth-dialogs
        v-model:login-visible="loginDialogVisible"
        v-model:register-visible="registerDialogVisible"
        login-title="管理者登入"
        login-audience="admin"
        register-title="建立帳號"
        register-context="staff_self_register"
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
        default-context="staff_invited_register"
        :allowed-contexts="['user_invited_register', 'staff_invited_register']"
        :auth-headers="buildAuthHeaders()"
    />
</template>

<script setup>
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';
import AppNavbar from '../../components/AppNavbar.vue';
import AuthDialogs from '../../components/AuthDialogs.vue';
import InviteDialog from '../../components/InviteDialog.vue';
import ProfileDialog from '../../components/ProfileDialog.vue';
import { useAuthSession } from '../../composables/useAuthSession';

const { t } = useI18n();

const guestNavbarActions = [
    {
        key: 'login',
        label: '登入',
        variant: 'ghost',
    },
    {
        key: 'register',
        label: '註冊',
        variant: 'primary',
    },
];

const authMenuItems = [
    {
        key: 'profile',
        label: '個人資料',
    },
    {
        key: 'invite',
        label: '邀請',
    },
    {
        key: 'logout',
        label: '登出',
    },
];

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

const navbarActions = computed(() => {
    return isAuthenticated.value ? [] : guestNavbarActions;
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
    if (actionKey === 'login') {
        openLoginDialog();
    }

    if (actionKey === 'register') {
        openRegisterDialog();
    }

    if (actionKey === 'profile') {
        openProfileDialog();
    }

    if (actionKey === 'invite') {
        openInviteDialog();
    }

    if (actionKey === 'logout') {
        submitLogout();
    }
};

const handleLoggedIn = async (token) => {
    await applyLoginToken(token);

    if (!isStaff.value) {
        ElMessage.error('你沒有後台權限，將導回首頁');
        window.location.href = '/';
    }
};

const submitLogout = async () => {
    await logout();
    ElMessage.success('已登出');
};

onMounted(async () => {
    await restoreSession();

    if (isAuthenticated.value && !isStaff.value) {
        ElMessage.error('你沒有後台權限，將導回首頁');
        window.location.href = '/';
    }
});
</script>
