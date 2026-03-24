<template>
    <main class="spa-root">
        <app-navbar
            brand-href="/"
            brand-label="PracticeServer"
            nav-label="public navigation"
            :actions="navbarActions"
            :authenticated="isAuthenticated"
            :user-status-label="userStatusLabel"
            :user-label="userLabel"
            :menu-items="authMenuItems"
            @action="handleNavbarAction"
        />

        <section class="spa-wrap">
            <div class="spa-content">
                <el-card v-if="!isVertexChatPage" class="spa-panel" shadow="hover">
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
                        <el-button plain @click="goToVertexChat">前往 Vertex AI 對話</el-button>
                    </el-space>
                </el-card>

                <el-card v-else class="spa-panel spa-chat-panel" shadow="hover">
                    <template #header>
                        <div class="spa-panel-header spa-chat-header">
                            <div>
                                <el-tag type="warning">Vertex AI</el-tag>
                                <span class="spa-panel-title">簡易對話頁</span>
                            </div>
                            <el-button plain @click="goHome">回首頁</el-button>
                        </div>
                    </template>

                    <div class="spa-chat-history">
                        <article
                            v-for="(message, index) in chatMessages"
                            :key="`${message.role}-${index}`"
                            class="spa-chat-message"
                            :class="message.role === 'user' ? 'spa-chat-message-user' : 'spa-chat-message-model'"
                        >
                            <div class="spa-chat-role">{{ message.role === 'user' ? '你' : 'Vertex AI' }}</div>
                            <p class="spa-chat-text">{{ message.content }}</p>
                        </article>
                    </div>

                    <div class="spa-chat-form">
                        <el-input
                            v-model="prompt"
                            type="textarea"
                            :rows="4"
                            resize="none"
                            placeholder="輸入問題，送出後會呼叫 Vertex AI"
                            @keydown.ctrl.enter="submitChat"
                        />
                        <div class="spa-chat-actions">
                            <span class="spa-chat-hint">Ctrl + Enter 可送出</span>
                            <el-button type="primary" :loading="isSending" @click="submitChat">送出提問</el-button>
                        </div>
                    </div>
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
import axios from 'axios';
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
const prompt = ref('');
const isSending = ref(false);
const chatMessages = ref([
    {
        role: 'model',
        content: '你好，這裡是 Vertex AI 測試頁。請直接輸入問題。',
    },
]);

const isVertexChatPage = computed(() => {
    return window.location.pathname.startsWith('/google/vertex/chat');
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

const goToVertexChat = () => {
    window.location.assign('/google/vertex/chat');
};

const goHome = () => {
    window.location.assign('/');
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

const submitChat = async () => {
    const normalizedPrompt = prompt.value.trim();

    if (normalizedPrompt === '' || isSending.value) {
        return;
    }

    const history = chatMessages.value
        .filter((message) => message.role === 'user' || message.role === 'model')
        .map((message) => ({
            role: message.role,
            content: message.content,
        }));

    chatMessages.value.push({
        role: 'user',
        content: normalizedPrompt,
    });

    prompt.value = '';
    isSending.value = true;

    try {
        const response = await axios.post('/api/google/vertex/chat', {
            prompt: normalizedPrompt,
            messages: history,
        });

        chatMessages.value.push({
            role: 'model',
            content: response.data?.data?.reply ?? 'Vertex AI 沒有回傳內容。',
        });
    } catch (error) {
        const errorMessage = error?.response?.data?.error ?? 'Vertex AI 呼叫失敗';

        chatMessages.value.push({
            role: 'model',
            content: `錯誤：${errorMessage}`,
        });

        ElMessage.error(errorMessage);
    } finally {
        isSending.value = false;
    }
};

onMounted(async () => {
    await restoreSession();

    await enforcePublicOnlyAccount();
});
</script>
