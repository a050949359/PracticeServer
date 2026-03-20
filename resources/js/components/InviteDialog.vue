<template>
    <el-dialog
        v-model="dialogVisible"
        :title="t('inviteDialog.title')"
        width="min(560px, 92vw)"
        align-center
        destroy-on-close
    >
        <el-form ref="inviteFormRef" :model="inviteForm" :rules="inviteRules" label-position="top">
            <el-form-item :label="t('inviteDialog.form.nameLabel')" prop="name">
                <el-input v-model="inviteForm.name" :placeholder="t('inviteDialog.form.namePlaceholder')" />
            </el-form-item>

            <el-form-item :label="t('inviteDialog.form.emailLabel')" prop="email">
                <el-input v-model="inviteForm.email" type="email" placeholder="name@example.com" />
            </el-form-item>

            <el-form-item :label="t('inviteDialog.form.contextLabel')" prop="context">
                <el-select v-model="inviteForm.context" :placeholder="t('inviteDialog.form.contextPlaceholder')" style="width: 100%">
                    <el-option
                        v-for="option in inviteContextOptions"
                        :key="option.value"
                        :label="option.label"
                        :value="option.value"
                    />
                </el-select>
            </el-form-item>
        </el-form>

        <template #footer>
            <el-space>
                <el-button @click="dialogVisible = false">{{ t('common.cancel') }}</el-button>
                <el-button type="primary" :loading="inviting" @click="submitInvite">{{ t('inviteDialog.actions.submit') }}</el-button>
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
    visible: {
        type: Boolean,
        default: false,
    },
    defaultContext: {
        type: String,
        default: 'user_invited_register',
    },
    authHeaders: {
        type: Object,
        default: () => ({}),
    },
    allowedContexts: {
        type: Array,
        default: () => ['user_invited_register', 'staff_invited_register'],
    },
});

const emit = defineEmits(['update:visible', 'invited']);

const inviteFormRef = ref(null);
const inviting = ref(false);

const inviteForm = reactive({
    name: '',
    email: '',
    context: props.defaultContext,
});

const inviteContextOptions = computed(() => {
    const inviteContextCatalog = {
        user_invited_register: {
            label: t('inviteDialog.contexts.userInvitedRegister'),
            value: 'user_invited_register',
        },
        staff_invited_register: {
            label: t('inviteDialog.contexts.staffInvitedRegister'),
            value: 'staff_invited_register',
        },
    };

    return props.allowedContexts.map((context) => inviteContextCatalog[context]).filter(Boolean);
});

const resolveInviteContext = () => {
    if (props.allowedContexts.includes(inviteForm.context)) {
        return inviteForm.context;
    }

    if (props.allowedContexts.includes(props.defaultContext)) {
        return props.defaultContext;
    }

    return props.allowedContexts[0] ?? 'user_invited_register';
};

const inviteRules = {
    email: [
        { required: true, message: t('inviteDialog.validation.emailRequired'), trigger: 'blur' },
        { type: 'email', message: t('inviteDialog.validation.emailInvalid'), trigger: 'blur' },
    ],
    context: [{ required: true, message: t('inviteDialog.validation.contextRequired'), trigger: 'change' }],
};

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => {
        emit('update:visible', value);
    },
});

const resetInviteForm = () => {
    inviteForm.name = '';
    inviteForm.email = '';
    inviteForm.context = resolveInviteContext();
    inviteFormRef.value?.clearValidate();
};

const submitInvite = async () => {
    const form = inviteFormRef.value;

    if (!form) {
        return;
    }

    try {
        await form.validate();
        inviting.value = true;

        await axios.post(
            '/api/admin/v1/invitations',
            {
                name: inviteForm.name || null,
                email: inviteForm.email,
                context: inviteForm.context,
            },
            {
                headers: props.authHeaders,
            }
        );

        ElMessage.success(t('inviteDialog.messages.success'));
        dialogVisible.value = false;
        resetInviteForm();
        emit('invited');
    } catch (error) {
        const message = error?.response?.data?.message ?? t('inviteDialog.messages.failure');
        ElMessage.error(message);
    } finally {
        inviting.value = false;
    }
};

watch(
    () => props.visible,
    (visible) => {
        if (visible) {
            resetInviteForm();
        }
    }
);

watch(
    () => props.defaultContext,
    () => {
        inviteForm.context = resolveInviteContext();
    }
);

watch(
    () => props.allowedContexts,
    () => {
        inviteForm.context = resolveInviteContext();
    }
);
</script>
