<template>
    <main class="spa-root">
        <app-navbar
            brand-href="/"
            brand-label="PracticeServer"
            nav-label="public navigation"
            :actions="navbarActions"
            :authenticated="isAuthenticated"
            :user-label="userLabel"
            :menu-items="authMenuItems"
            @action="handleNavbarAction"
        />

        <section class="spa-wrap">
            <div class="spa-content">
                <el-card class="spa-panel" shadow="hover">
                    <template #header>
                        <div class="spa-panel-header">
                            <el-tag type="success">Public Area</el-tag>
                            <span class="spa-panel-title">前台入口</span>
                        </div>
                    </template>

                    <h1 class="spa-title">這是一般使用者頁面</h1>
                    <p class="spa-description">
                        前台與後台已分開登入。這裡提供一般使用者自己的登入與註冊流程。
                    </p>

                    <el-space class="spa-actions-row" wrap>
                        <el-button v-if="!isAuthenticated" plain @click="openLoginDialog">立即登入</el-button>
                        <el-button v-if="!isAuthenticated" type="primary" @click="openRegisterDialog">立即註冊</el-button>
                        <el-button v-if="isAuthenticated" plain @click="openProfileDialog">個人資料</el-button>
                        <el-button v-if="isAuthenticated" type="primary" @click="openInviteDialog">發送邀請</el-button>
                        <el-button plain disabled>即將新增前台功能</el-button>
                    </el-space>
                </el-card>
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

    <profile-dialog v-model:visible="profileDialogVisible" :user="currentUser" />

    <invite-dialog
        v-model:visible="inviteDialogVisible"
        default-context="user_invited_register"
        :allowed-contexts="['user_invited_register']"
        :auth-headers="buildAuthHeaders()"
    />
</template>

<script setup>
import { ElMessage } from 'element-plus';
import AppNavbar from '../../components/AppNavbar.vue';
import AuthDialogs from '../../components/AuthDialogs.vue';
import InviteDialog from '../../components/InviteDialog.vue';
import ProfileDialog from '../../components/ProfileDialog.vue';
import { useAuthSession } from '../../composables/useAuthSession';

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

const { currentUser, isAuthenticated, isStaff, userLabel, buildAuthHeaders, applyLoginToken, logout, restoreSession, loadCurrentUser } =
    useAuthSession();

const navbarActions = computed(() => {
    return isAuthenticated.value ? [] : guestNavbarActions;
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
</script>
