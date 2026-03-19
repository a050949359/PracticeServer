<template>
    <el-dialog
        v-model="loginDialogVisible"
        :title="loginTitle"
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
        :title="registerTitle"
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

const props = defineProps({
    loginVisible: {
        type: Boolean,
        default: false,
    },
    registerVisible: {
        type: Boolean,
        default: false,
    },
    registerContext: {
        type: String,
        default: 'user_self_register',
    },
    loginTitle: {
        type: String,
        default: '登入',
    },
    registerTitle: {
        type: String,
        default: '建立帳號',
    },
});

const emit = defineEmits(['update:loginVisible', 'update:registerVisible', 'logged-in']);

const loginFormRef = ref(null);
const registerFormRef = ref(null);
const loggingIn = ref(false);
const registering = ref(false);

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

const loginDialogVisible = computed({
    get: () => props.loginVisible,
    set: (value) => {
        emit('update:loginVisible', value);
    },
});

const registerDialogVisible = computed({
    get: () => props.registerVisible,
    set: (value) => {
        emit('update:registerVisible', value);
    },
});

const resetLoginForm = () => {
    loginForm.email = '';
    loginForm.password = '';
    loginFormRef.value?.clearValidate();
};

const resetRegisterForm = () => {
    registerForm.name = '';
    registerForm.email = '';
    registerForm.password = '';
    registerForm.password_confirmation = '';
    registerFormRef.value?.clearValidate();
};

const submitLogin = async () => {
    const form = loginFormRef.value;

    if (!form) {
        return;
    }

    try {
        await form.validate();
        loggingIn.value = true;

        const response = await axios.post('/api/auth/login', {
            email: loginForm.email,
            password: loginForm.password,
        });

        const token = response?.data?.token;
        if (!token) {
            throw new Error('Missing token');
        }

        ElMessage.success('登入成功');
        loginDialogVisible.value = false;
        resetLoginForm();
        emit('logged-in', token);
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
            context: props.registerContext,
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

watch(
    () => props.loginVisible,
    (visible) => {
        if (visible) {
            resetLoginForm();
        }
    }
);

watch(
    () => props.registerVisible,
    (visible) => {
        if (visible) {
            resetRegisterForm();
        }
    }
);
</script>
