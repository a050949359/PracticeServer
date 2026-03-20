<template>
    <el-dialog v-model="dialogVisible" :title="t('profileDialog.title')" width="min(520px, 92vw)" align-center>
        <el-descriptions :column="1" border>
            <el-descriptions-item :label="t('profileDialog.fields.name')">{{ user?.name ?? '-' }}</el-descriptions-item>
            <el-descriptions-item label="Email">{{ user?.email ?? '-' }}</el-descriptions-item>
            <el-descriptions-item :label="t('profileDialog.fields.id')">{{ user?.id ?? '-' }}</el-descriptions-item>
        </el-descriptions>
    </el-dialog>
</template>

<script setup>
import { useI18n } from 'vue-i18n';

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

const emit = defineEmits(['update:visible']);

const dialogVisible = computed({
    get: () => props.visible,
    set: (value) => {
        emit('update:visible', value);
    },
});
</script>
