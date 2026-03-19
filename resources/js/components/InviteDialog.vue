<template>
    <el-dialog
        v-model="dialogVisible"
        title="發送註冊邀請"
        width="min(560px, 92vw)"
        align-center
        destroy-on-close
    >
        <el-form ref="inviteFormRef" :model="inviteForm" :rules="inviteRules" label-position="top">
            <el-form-item label="受邀者名稱" prop="name">
                <el-input v-model="inviteForm.name" placeholder="可留空" />
            </el-form-item>

            <el-form-item label="受邀者 Email" prop="email">
                <el-input v-model="inviteForm.email" type="email" placeholder="name@example.com" />
            </el-form-item>

            <el-form-item label="邀請類型" prop="context">
                <el-select v-model="inviteForm.context" placeholder="選擇邀請類型" style="width: 100%">
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
                <el-button @click="dialogVisible = false">取消</el-button>
                <el-button type="primary" :loading="inviting" @click="submitInvite">送出邀請</el-button>
            </el-space>
        </template>
    </el-dialog>
</template>

<script setup>
import axios from 'axios';
import { ElMessage } from 'element-plus';

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

const inviteContextCatalog = {
    user_invited_register: {
        label: '一般使用者邀請',
        value: 'user_invited_register',
    },
    staff_invited_register: {
        label: '員工邀請',
        value: 'staff_invited_register',
    },
};

const inviteContextOptions = computed(() => {
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
        { required: true, message: '請輸入 Email', trigger: 'blur' },
        { type: 'email', message: 'Email 格式不正確', trigger: 'blur' },
    ],
    context: [{ required: true, message: '請選擇邀請類型', trigger: 'change' }],
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

        ElMessage.success('邀請已送出');
        dialogVisible.value = false;
        resetInviteForm();
        emit('invited');
    } catch (error) {
        const message = error?.response?.data?.message ?? '邀請送出失敗，請稍後再試';
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
