<template>
    <main class="spa-root">
        <header class="spa-topbar">
            <div class="spa-topbar-inner">
                <a href="/" class="spa-brand">PracticeServer</a>
                <nav class="spa-actions" aria-label="auth actions">
                    <el-button plain class="spa-btn-ghost" @click="openLoginDialog">登入</el-button>
                    <el-button type="primary" class="spa-btn-primary" @click="openRegisterDialog">註冊</el-button>
                </nav>
            </div>
        </header>

        <section class="spa-wrap">
            <div class="spa-content">
                <el-card class="spa-panel" shadow="hover">
                    <template #header>
                        <div class="spa-panel-header">
                            <el-tag effect="dark" type="info">CSR + Vue</el-tag>
                            <span class="spa-panel-title">Element Plus 已接上</span>
                        </div>
                    </template>

                    <h1 class="spa-title">這是前端渲染頁面</h1>
                    <p class="spa-description">
                        登入與註冊都改為畫面中央彈出式表單，送出後會呼叫對應 API。
                    </p>

                    <el-space class="spa-actions-row" wrap>
                        <el-button plain @click="openLoginDialog">立即登入</el-button>
                        <el-button type="primary" @click="openRegisterDialog">立即註冊</el-button>
                        <el-button plain>查看更多</el-button>
                    </el-space>
                </el-card>
            </div>
        </section>
    </main>

    <el-dialog
        v-model="loginDialogVisible"
        title="登入"
        width="min(520px, 92vw)"
        align-center
        destroy-on-close
    >
        <el-form ref="loginFormRef" :model="loginForm" :rules="loginRules" label-position="top">
            <el-form-item label="Email" prop="email">
                <el-input v-model="loginForm.email" type="email" placeholder="name@example.com" />
            </el-form-item>

            <el-form-item label="密碼" prop="password">
                <el-input v-model="loginForm.password" type="password" show-password placeholder="請輸入密碼" />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-space>
                <el-button @click="loginDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="loggingIn" @click="submitLogin">登入</el-button>
            </el-space>
        </template>
    </el-dialog>

    <el-dialog
        v-model="registerDialogVisible"
        title="建立帳號"
        width="min(560px, 92vw)"
        align-center
        destroy-on-close
    >
        <el-form ref="registerFormRef" :model="registerForm" :rules="registerRules" label-position="top">
            <el-form-item label="名稱" prop="name">
                <el-input v-model="registerForm.name" placeholder="請輸入名稱" />
            </el-form-item>

            <el-form-item label="Email" prop="email">
                <el-input v-model="registerForm.email" type="email" placeholder="name@example.com" />
            </el-form-item>

            <el-form-item label="密碼" prop="password">
                <el-input v-model="registerForm.password" type="password" show-password placeholder="至少 8 碼" />
            </el-form-item>

            <el-form-item label="確認密碼" prop="password_confirmation">
                <el-input
                    v-model="registerForm.password_confirmation"
                    type="password"
                    show-password
                    placeholder="請再次輸入密碼"
                />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-space>
                <el-button @click="registerDialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="registering" @click="submitRegister">建立帳號</el-button>
            </el-space>
        </template>
    </el-dialog>
</template>

<script setup>
import axios from 'axios';
import { ElMessage } from 'element-plus';

const loginDialogVisible = ref(false);
const loggingIn = ref(false);
const loginFormRef = ref(null);
const registerDialogVisible = ref(false);
const registering = ref(false);
const registerFormRef = ref(null);

const loginForm = reactive({
    email: '',
    password: '',
});

const registerForm = reactive({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
});

const loginRules = {
    email: [
        { required: true, message: '請輸入 Email', trigger: 'blur' },
        { type: 'email', message: 'Email 格式不正確', trigger: 'blur' },
    ],
    password: [{ required: true, message: '請輸入密碼', trigger: 'blur' }],
};

const registerRules = {
    name: [
        { required: true, message: '請輸入名稱', trigger: 'blur' },
        { min: 2, message: '名稱至少 2 個字', trigger: 'blur' },
    ],
    email: [
        { required: true, message: '請輸入 Email', trigger: 'blur' },
        { type: 'email', message: 'Email 格式不正確', trigger: 'blur' },
    ],
    password: [
        { required: true, message: '請輸入密碼', trigger: 'blur' },
        { min: 8, message: '密碼至少 8 碼', trigger: 'blur' },
    ],
    password_confirmation: [
        { required: true, message: '請輸入確認密碼', trigger: 'blur' },
        {
            validator: (_rule, value, callback) => {
                if (value !== registerForm.password) {
                    callback(new Error('兩次密碼不一致'));
                    return;
                }

                callback();
            },
            trigger: 'blur',
        },
    ],
};

const resetLoginForm = () => {
    loginForm.email = '';
    loginForm.password = '';
    loginFormRef.value?.clearValidate();
};

const openLoginDialog = () => {
    resetLoginForm();
    loginDialogVisible.value = true;
};

const resetRegisterForm = () => {
    registerForm.name = '';
    registerForm.email = '';
    registerForm.password = '';
    registerForm.password_confirmation = '';
    registerFormRef.value?.clearValidate();
};

const openRegisterDialog = () => {
    resetRegisterForm();
    registerDialogVisible.value = true;
};

const submitLogin = async () => {
    const form = loginFormRef.value;

    if (!form) {
        return;
    }

    try {
        await form.validate();
        loggingIn.value = true;

        await axios.post('/api/auth/login', {
            email: loginForm.email,
            password: loginForm.password,
        });

        ElMessage.success('登入成功');
        loginDialogVisible.value = false;
        resetLoginForm();
    } catch (error) {
        const message = error?.response?.data?.message ?? '登入失敗，請檢查帳號密碼';
        ElMessage.error(message);
    } finally {
        loggingIn.value = false;
    }
};

const submitRegister = async () => {
    const form = registerFormRef.value;

    if (!form) {
        return;
    }

    try {
        await form.validate();
        registering.value = true;

        await axios.post('/api/auth/register', {
            name: registerForm.name,
            email: registerForm.email,
            password: registerForm.password,
            password_confirmation: registerForm.password_confirmation,
        });

        ElMessage.success('註冊成功，請使用新帳號登入');
        registerDialogVisible.value = false;
        resetRegisterForm();
    } catch (error) {
        const message = error?.response?.data?.message ?? '註冊失敗，請稍後再試';
        ElMessage.error(message);
    } finally {
        registering.value = false;
    }
};
</script>
