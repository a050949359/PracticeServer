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

            <el-form-item :label="t('authDialogs.form.passwordLabel')" prop="password">
                <el-input
                    v-model="loginForm.password"
                    type="password"
                    show-password
                    :placeholder="t('authDialogs.form.passwordPlaceholder')"
                />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-space>
                <el-button @click="loginDialogVisible = false">{{ t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="loggingIn" @click="submitLogin">{{ t('authDialogs.actions.login') }}</el-button>
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
            <el-form-item :label="t('authDialogs.form.nameLabel')" prop="name">
                <el-input v-model="registerForm.name" :placeholder="t('authDialogs.form.namePlaceholder')" />
            </el-form-item>

            <el-form-item label="Email" prop="email">
                <el-input v-model="registerForm.email" type="email" placeholder="name@example.com" />
            </el-form-item>

            <el-form-item :label="t('authDialogs.form.passwordLabel')" prop="password">
                <el-input
                    v-model="registerForm.password"
                    type="password"
                    show-password
                    :placeholder="t('authDialogs.form.passwordMinPlaceholder')"
                />
            </el-form-item>

            <el-form-item :label="t('authDialogs.form.passwordConfirmationLabel')" prop="password_confirmation">
                <el-input
                    v-model="registerForm.password_confirmation"
                    type="password"
                    show-password
                    :placeholder="t('authDialogs.form.passwordConfirmationPlaceholder')"
                />
            </el-form-item>
        </el-form>

        <template #footer>
            <el-space>
                <el-button @click="registerDialogVisible = false">{{ t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="registering" @click="submitRegister">{{ t('authDialogs.actions.register') }}</el-button>
            </el-space>
        </template>
    </el-dialog>
</template>

<script setup>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';

const { t } = useI18n();

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
    loginAudience: {
        type: String,
        default: 'public',
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
        { required: true, message: t('authDialogs.validation.emailRequired'), trigger: 'blur' },
        { type: 'email', message: t('authDialogs.validation.emailInvalid'), trigger: 'blur' },
    ],
    password: [{ required: true, message: t('authDialogs.validation.passwordRequired'), trigger: 'blur' }],
};

const registerRules = {
    name: [
        { required: true, message: t('authDialogs.validation.nameRequired'), trigger: 'blur' },
        { min: 2, message: t('authDialogs.validation.nameMin'), trigger: 'blur' },
    ],
    email: [
        { required: true, message: t('authDialogs.validation.emailRequired'), trigger: 'blur' },
        { type: 'email', message: t('authDialogs.validation.emailInvalid'), trigger: 'blur' },
    ],
    password: [
        { required: true, message: t('authDialogs.validation.passwordRequired'), trigger: 'blur' },
        { min: 8, message: t('authDialogs.validation.passwordMin'), trigger: 'blur' },
    ],
    password_confirmation: [
        { required: true, message: t('authDialogs.validation.passwordConfirmationRequired'), trigger: 'blur' },
        {
            validator: (_rule, value, callback) => {
                if (value !== registerForm.password) {
                    callback(new Error(t('authDialogs.validation.passwordMismatch')));
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
            audience: props.loginAudience,
        });

        const token = response?.data?.token;
        if (!token) {
            throw new Error('Missing token');
        }

        ElMessage.success(t('authDialogs.messages.loginSuccess'));
        loginDialogVisible.value = false;
        resetLoginForm();
        emit('logged-in', token);
    } catch (error) {
        const errorCode = error?.response?.data?.code;
        const errorStatus = error?.response?.status;
        const messageByCode = {
            invalid_credentials: t('authDialogs.messages.invalidCredentials'),
            forbidden_admin_only: t('authDialogs.messages.forbiddenAdminOnly'),
            forbidden_public_only: t('authDialogs.messages.forbiddenPublicOnly'),
        };
        let message = messageByCode[errorCode] ?? null;

        if (!message && errorStatus === 403) {
            message = props.loginAudience === 'admin'
                ? t('authDialogs.messages.forbiddenAdminOnly')
                : t('authDialogs.messages.forbiddenPublicOnly');
        }

        message = message ?? error?.response?.data?.message ?? t('authDialogs.messages.invalidCredentials');
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

        ElMessage.success(t('authDialogs.messages.registerSuccess'));
        registerDialogVisible.value = false;
        resetRegisterForm();
    } catch (error) {
        const message = error?.response?.data?.message ?? t('authDialogs.messages.registerFailure');
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
