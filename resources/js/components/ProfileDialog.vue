<template>
    <el-dialog v-model="dialogVisible" :title="t('profileDialog.title')" width="min(520px, 92vw)" align-center>
        <el-form label-position="top" @submit.prevent="submitProfile">
            <el-form-item :label="t('profileDialog.fields.name')">
                <el-input v-model="form.name" :placeholder="t('profileDialog.placeholders.name')" maxlength="255" />
            </el-form-item>

            <el-form-item :label="t('profileDialog.fields.email')">
                <el-input :model-value="user?.email ?? '-'" disabled />
            </el-form-item>

            <el-form-item :label="t('profileDialog.fields.verification')">
                <el-tag :type="isEmailVerified ? 'success' : 'warning'">
                    {{ isEmailVerified ? t('profileDialog.verification.verified') : t('profileDialog.verification.unverified') }}
                </el-tag>
            </el-form-item>

            <el-divider />

            <el-form-item :label="t('profileDialog.fields.currentPassword')">
                <el-input
                    v-model="passwordForm.current_password"
                    type="password"
                    show-password
                    :placeholder="t('profileDialog.placeholders.currentPassword')"
                />
            </el-form-item>

            <el-form-item :label="t('profileDialog.fields.newPassword')">
                <el-input
                    v-model="passwordForm.password"
                    type="password"
                    show-password
                    :placeholder="t('profileDialog.placeholders.newPassword')"
                />
            </el-form-item>

            <el-form-item :label="t('profileDialog.fields.passwordConfirmation')">
                <el-input
                    v-model="passwordForm.password_confirmation"
                    type="password"
                    show-password
                    :placeholder="t('profileDialog.placeholders.passwordConfirmation')"
                />
            </el-form-item>

            <div class="profile-dialog-actions">
                <el-button type="warning" plain :loading="changingPassword" @click="submitPasswordChange">
                    {{ t('profileDialog.actions.changePassword') }}
                </el-button>
                <el-button @click="dialogVisible = false">{{ t('common.cancel') }}</el-button>
                <el-button type="primary" native-type="submit" :loading="submitting">{{ t('profileDialog.actions.save') }}</el-button>
            </div>
        </el-form>
    </el-dialog>
</template>

<script setup>
import { ElMessage } from 'element-plus';
import { useI18n } from 'vue-i18n';
import { validatePasswordPolicy } from '../utils/passwordPolicy';

const { t } = useI18n();

const props = defineProps({
    visible: {
        type: Boolean,
        default: false,
    },
    user: {
        type: Object,
        default: () => null,
    },
});

const emit = defineEmits(['update:visible', 'submit', 'change-password']);

const form = ref({
    name: '',
});

const submitting = ref(false);
const changingPassword = ref(false);

const passwordForm = ref({
    current_password: '',
    password: '',
    password_confirmation: '',
});

watch(
    () => props.user,
    (user) => {
        form.value.name = user?.name ?? '';
    },
    {
        immediate: true,
    }
);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => {
        emit('update:visible', value);
    },
});

const isEmailVerified = computed(() => {
    return Boolean(props.user?.email_verified_at);
});

const submitProfile = async () => {
    if (!form.value.name.trim()) {
        return;
    }

    submitting.value = true;
    try {
        await emit('submit', {
            name: form.value.name.trim(),
        });
    } finally {
        submitting.value = false;
    }
};

const resetPasswordForm = () => {
    passwordForm.value.current_password = '';
    passwordForm.value.password = '';
    passwordForm.value.password_confirmation = '';
};

const submitPasswordChange = async () => {
    if (!passwordForm.value.current_password || !passwordForm.value.password || !passwordForm.value.password_confirmation) {
        return;
    }

    const passwordPolicyError = validatePasswordPolicy(passwordForm.value.password);
    if (passwordPolicyError) {
        const errorMessageMap = {
            min_length: t('common.passwordPolicy.minLength'),
            mixed_case: t('common.passwordPolicy.mixedCase'),
            numbers: t('common.passwordPolicy.numbers'),
            symbols: t('common.passwordPolicy.symbols'),
        };

        ElMessage.error(errorMessageMap[passwordPolicyError] ?? t('profileDialog.messages.passwordUpdateFailure'));
        return;
    }

    if (passwordForm.value.password !== passwordForm.value.password_confirmation) {
        return;
    }

    changingPassword.value = true;
    try {
        await emit('change-password', {
            current_password: passwordForm.value.current_password,
            password: passwordForm.value.password,
            password_confirmation: passwordForm.value.password_confirmation,
        });

        resetPasswordForm();
    } finally {
        changingPassword.value = false;
    }
};
</script>

<style scoped>
.profile-dialog-actions {
    display: flex;
    justify-content: flex-end;
    gap: 0.5rem;
}
</style>
