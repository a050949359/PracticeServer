<template>
    <el-dialog v-model="dialogVisible" title="個人資料" width="min(520px, 92vw)" align-center>
        <el-descriptions :column="1" border>
            <el-descriptions-item label="名稱">{{ user?.name ?? '-' }}</el-descriptions-item>
            <el-descriptions-item label="Email">{{ user?.email ?? '-' }}</el-descriptions-item>
            <el-descriptions-item label="ID">{{ user?.id ?? '-' }}</el-descriptions-item>
        </el-descriptions>
    </el-dialog>
</template>

<script setup>
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
