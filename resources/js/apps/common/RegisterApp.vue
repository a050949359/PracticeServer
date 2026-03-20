<template>
    <main class="spa-root">
        <app-navbar
            brand-href="/"
            brand-label="PracticeServer"
            nav-label="register navigation"
            :actions="topActions"
        />

        <section class="spa-wrap">
            <div class="spa-content">
                <el-card class="spa-panel" shadow="hover">
                    <template #header>
                        <div class="spa-panel-header">
                            <el-tag :type="panelTagType">{{ panelTagLabel }}</el-tag>
                            <span class="spa-panel-title">{{ panelTitle }}</span>
                        </div>
                    </template>

                    <template v-if="isVerificationResultMode">
                        <el-alert
                            :type="verificationAlertType"
                            :closable="false"
                            :title="verificationResult.title"
                            :description="verificationResult.message"
                            show-icon
                        />

                        <p v-if="verificationResult.verifiedAtLabel" class="spa-description" style="margin-top: 1rem">
                            {{ t('register.verification.verifiedAt', { value: verificationResult.verifiedAtLabel }) }}
                        </p>

                        <el-space style="margin-top: 1rem">
                            <el-button v-if="verificationResult.redirectTo === '/admin'" type="primary" @click="goAdmin">
                                {{ t('register.verification.actions.admin') }}
                            </el-button>
                            <el-button v-else type="primary" @click="goHome">{{ t('register.verification.actions.home') }}</el-button>
                            <el-button v-if="verificationResult.redirectTo === '/admin'" @click="goHome">{{ t('register.verification.actions.home') }}</el-button>
                        </el-space>
                    </template>

                    <template v-else-if="isResetPasswordMode">
                        <el-alert
                            v-if="!resetPasswordParamsReady"
                            type="error"
                            :closable="false"
                            :title="t('register.resetPassword.missingParams')"
                            show-icon
                        />

                        <el-form
                            v-else
                            ref="resetPasswordFormRef"
                            :model="resetPasswordForm"
                            :rules="resetPasswordRules"
                            label-position="top"
                        >
                            <el-form-item :label="t('register.resetPassword.emailLabel')">
                                <el-input :model-value="resetPasswordEmail" disabled />
                            </el-form-item>

                            <el-form-item :label="t('register.resetPassword.passwordLabel')" prop="password">
                                <el-input
                                    v-model="resetPasswordForm.password"
                                    type="password"
                                    show-password
                                    :placeholder="t('register.resetPassword.passwordPlaceholder')"
                                />
                            </el-form-item>

                            <el-form-item :label="t('register.resetPassword.passwordConfirmationLabel')" prop="password_confirmation">
                                <el-input
                                    v-model="resetPasswordForm.password_confirmation"
                                    type="password"
                                    show-password
                                    :placeholder="t('register.resetPassword.passwordConfirmationPlaceholder')"
                                />
                            </el-form-item>

                            <el-space>
                                <el-button @click="goHome">{{ t('register.nav.home') }}</el-button>
                                <el-button type="primary" :loading="resetPasswordSubmitting" @click="submitResetPassword">
                                    {{ t('register.resetPassword.submit') }}
                                </el-button>
                            </el-space>
                        </el-form>
                    </template>

                    <el-skeleton v-else-if="invitationLoading" :rows="4" animated />

                    <el-alert
                        v-else-if="invitationError"
                        type="error"
                        :closable="false"
                        :title="invitationError"
                        show-icon
                    />

                    <template v-else-if="invitationInfo">
                        <p class="spa-description">
                            {{ t('register.invitation.forEmail', { email: invitationInfo.email }) }}
                        </p>
                        <p class="spa-description" style="margin-top: 0.5rem">
                            {{ t('register.invitation.invitedName', { name: invitationInfo.name || t('register.invitation.unknownName') }) }}
                        </p>

                        <el-form
                            ref="invitationFormRef"
                            :model="invitationForm"
                            :rules="invitationRules"
                            label-position="top"
                            style="margin-top: 1rem"
                        >
                            <el-form-item :label="t('register.invitation.password')" prop="password">
                                <el-input
                                    v-model="invitationForm.password"
                                    type="password"
                                    show-password
                                    :placeholder="t('register.invitation.passwordPlaceholder')"
                                />
                            </el-form-item>

                            <el-form-item :label="t('register.invitation.passwordConfirmation')" prop="password_confirmation">
                                <el-input
                                    v-model="invitationForm.password_confirmation"
                                    type="password"
                                    show-password
                                    :placeholder="t('register.invitation.passwordConfirmationPlaceholder')"
                                />
                            </el-form-item>

                            <el-space>
                                <el-button @click="goHome">{{ t('register.nav.home') }}</el-button>
                                <el-button
                                    type="primary"
                                    :loading="invitationSubmitting"
                                    @click="completeInvitationRegistration"
                                >
                                    {{ t('register.invitation.submit') }}
                                </el-button>
                            </el-space>
                        </el-form>
                    </template>
                </el-card>
            </div>
        </section>
    </main>
</template>

<script setup>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';
import AppNavbar from '../../components/AppNavbar.vue';
import { useAuthSession } from '../../composables/useAuthSession';
import { validatePasswordPolicy } from '../../utils/passwordPolicy';

const { applyLoginToken } = useAuthSession();
const { t, te, locale } = useI18n();

const topActions = computed(() => [
    {
        key: 'home',
        label: t('register.nav.home'),
        variant: 'ghost',
        href: '/',
    },
]);

const invitationFormRef = ref(null);
const resetPasswordFormRef = ref(null);
const invitationLoading = ref(false);
const invitationSubmitting = ref(false);
const resetPasswordSubmitting = ref(false);
const invitationError = ref('');
const invitationInfo = ref(null);

const isVerificationResultMode = computed(() => window.location.pathname.startsWith('/register/verify-email'));
const isResetPasswordMode = computed(() => window.location.pathname.startsWith('/register/reset-password'));

const verificationResult = computed(() => {
    const query = new URLSearchParams(window.location.search);
    const verifiedAt = query.get('verified_at');
    const code = query.get('code') ?? 'default';
    const translationCode = te(`register.verification.codes.${code}.title`) ? code : 'default';

    return {
        status: query.get('status') ?? 'info',
        code: translationCode,
        title: t(`register.verification.codes.${translationCode}.title`),
        message: t(`register.verification.codes.${translationCode}.message`),
        verifiedAtLabel: verifiedAt ? new Date(verifiedAt).toLocaleString(locale.value) : '',
        redirectTo: query.get('redirect_to') ?? '/',
    };
});

const verificationAlertType = computed(() => {
    if (verificationResult.value.status === 'success') {
        return 'success';
    }

    if (verificationResult.value.status === 'error') {
        return 'error';
    }

    return 'info';
});

const panelTagLabel = computed(() => {
    if (isVerificationResultMode.value) {
        return t('register.panel.verificationTag');
    }

    if (isResetPasswordMode.value) {
        return t('register.panel.resetPasswordTag');
    }

    return t('register.panel.invitationTag');
});

const panelTagType = computed(() => {
    if (!isVerificationResultMode.value) {
        return 'warning';
    }

    return verificationAlertType.value === 'error' ? 'danger' : verificationAlertType.value;
});

const panelTitle = computed(() => {
    if (isVerificationResultMode.value) {
        return t('register.panel.verificationTitle');
    }

    if (isResetPasswordMode.value) {
        return t('register.panel.resetPasswordTitle');
    }

    return t('register.panel.invitationTitle');
});

const invitationForm = reactive({
    password: '',
    password_confirmation: '',
});

const resetPasswordForm = reactive({
    password: '',
    password_confirmation: '',
});

const resetPasswordToken = computed(() => {
    const query = new URLSearchParams(window.location.search);

    return query.get('token') ?? '';
});

const resetPasswordEmail = computed(() => {
    const query = new URLSearchParams(window.location.search);

    return query.get('email') ?? '';
});

const resetPasswordExpires = computed(() => {
    const query = new URLSearchParams(window.location.search);

    return query.get('expires') ?? '';
});

const resetPasswordSignature = computed(() => {
    const query = new URLSearchParams(window.location.search);

    return query.get('signature') ?? '';
});

const resetPasswordParamsReady = computed(() => {
    return Boolean(
        resetPasswordToken.value
            && resetPasswordEmail.value
            && resetPasswordExpires.value
            && resetPasswordSignature.value,
    );
});

const invitationRules = computed(() => ({
    password: [
        { required: true, message: t('register.invitation.validation.passwordRequired'), trigger: 'blur' },
        {
            validator: (_rule, value, callback) => {
                const errorCode = validatePasswordPolicy(value ?? '');

                if (!errorCode) {
                    callback();
                    return;
                }

                const errorMessageMap = {
                    min_length: t('common.passwordPolicy.minLength'),
                    mixed_case: t('common.passwordPolicy.mixedCase'),
                    numbers: t('common.passwordPolicy.numbers'),
                    symbols: t('common.passwordPolicy.symbols'),
                };

                callback(new Error(errorMessageMap[errorCode] ?? t('register.invitation.validation.passwordRequired')));
            },
            trigger: 'blur',
        },
    ],
    password_confirmation: [
        { required: true, message: t('register.invitation.validation.passwordConfirmationRequired'), trigger: 'blur' },
        {
            validator: (_rule, value, callback) => {
                if (value !== invitationForm.password) {
                    callback(new Error(t('register.invitation.validation.passwordMismatch')));
                    return;
                }

                callback();
            },
            trigger: 'blur',
        },
    ],
}));

const resetPasswordRules = computed(() => ({
    password: [
        { required: true, message: t('register.resetPassword.validation.passwordRequired'), trigger: 'blur' },
        {
            validator: (_rule, value, callback) => {
                const errorCode = validatePasswordPolicy(value ?? '');

                if (!errorCode) {
                    callback();
                    return;
                }

                const errorMessageMap = {
                    min_length: t('common.passwordPolicy.minLength'),
                    mixed_case: t('common.passwordPolicy.mixedCase'),
                    numbers: t('common.passwordPolicy.numbers'),
                    symbols: t('common.passwordPolicy.symbols'),
                };

                callback(new Error(errorMessageMap[errorCode] ?? t('register.resetPassword.validation.passwordRequired')));
            },
            trigger: 'blur',
        },
    ],
    password_confirmation: [
        { required: true, message: t('register.resetPassword.validation.passwordConfirmationRequired'), trigger: 'blur' },
        {
            validator: (_rule, value, callback) => {
                if (value !== resetPasswordForm.password) {
                    callback(new Error(t('register.resetPassword.validation.passwordMismatch')));
                    return;
                }

                callback();
            },
            trigger: 'blur',
        },
    ],
}));

const invitationToken = computed(() => {
    const query = new URLSearchParams(window.location.search);

    return query.get('token') ?? '';
});

const invitationExpires = computed(() => {
    const query = new URLSearchParams(window.location.search);

    return query.get('expires') ?? '';
});

const invitationSignature = computed(() => {
    const query = new URLSearchParams(window.location.search);

    return query.get('signature') ?? '';
});

const goHome = () => {
    window.location.href = '/';
};

const goAdmin = () => {
    window.location.href = '/admin';
};

const resolveInvitationApiErrorMessage = (error, fallbackKey = 'register.invitation.apiErrors.default') => {
    const responseCode = error?.response?.data?.code;

    if (responseCode && te(`register.invitation.apiErrors.${responseCode}`)) {
        return t(`register.invitation.apiErrors.${responseCode}`);
    }

    return t(fallbackKey);
};

const loadInvitation = async () => {
    if (!invitationToken.value || !invitationExpires.value || !invitationSignature.value) {
        invitationError.value = t('register.invitation.missingToken');
        return;
    }

    try {
        invitationLoading.value = true;
        invitationError.value = '';

        const response = await axios.get(`/api/auth/invitations/${invitationToken.value}`, {
            params: {
                expires: Number(invitationExpires.value),
                signature: invitationSignature.value,
            },
        });
        invitationInfo.value = response?.data?.invitation ?? null;
    } catch (error) {
        invitationInfo.value = null;
        invitationError.value = resolveInvitationApiErrorMessage(error, 'register.invitation.invalid');
    } finally {
        invitationLoading.value = false;
    }
};

const completeInvitationRegistration = async () => {
    const form = invitationFormRef.value;

    if (!form) {
        return;
    }

    try {
        await form.validate();
        invitationSubmitting.value = true;

        const response = await axios.post('/api/auth/register/invitation', {
            token: invitationToken.value,
            expires: Number(invitationExpires.value),
            signature: invitationSignature.value,
            password: invitationForm.password,
            password_confirmation: invitationForm.password_confirmation,
        });

        const token = response?.data?.token;
        if (token) {
            await applyLoginToken(token);
        }

        ElMessage.success(t('register.invitation.success'));

        const redirectTo = response?.data?.redirect_to ?? '/';
        window.location.href = redirectTo;
    } catch (error) {
        const message = resolveInvitationApiErrorMessage(error, 'register.invitation.failure');
        ElMessage.error(message);
    } finally {
        invitationSubmitting.value = false;
    }
};

const submitResetPassword = async () => {
    if (!resetPasswordParamsReady.value) {
        ElMessage.error(t('register.resetPassword.missingParams'));
        return;
    }

    const form = resetPasswordFormRef.value;

    if (!form) {
        return;
    }

    try {
        await form.validate();
        resetPasswordSubmitting.value = true;

        await axios.post('/api/auth/password/reset', {
            token: resetPasswordToken.value,
            email: resetPasswordEmail.value,
            expires: Number(resetPasswordExpires.value),
            signature: resetPasswordSignature.value,
            password: resetPasswordForm.password,
            password_confirmation: resetPasswordForm.password_confirmation,
        });

        ElMessage.success(t('register.resetPassword.success'));
        goHome();
    } catch (error) {
        const message = error?.response?.data?.message ?? t('register.resetPassword.failure');
        ElMessage.error(message);
    } finally {
        resetPasswordSubmitting.value = false;
    }
};

onMounted(async () => {
    if (isVerificationResultMode.value || isResetPasswordMode.value) {
        return;
    }

    await loadInvitation();
});
</script>
