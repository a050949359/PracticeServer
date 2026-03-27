<template>
    <el-card class="spa-panel spa-chat-panel" shadow="hover">
        <template #header>
            <div class="spa-panel-header spa-chat-header">
                <div>
                    <el-tag type="warning">Queue / RabbitMQ</el-tag>
                    <span class="spa-panel-title">CSV 匯出</span>
                </div>
                <el-button plain @click="goAdminHome">回後台首頁</el-button>
            </div>
        </template>

        <p class="spa-description">檔名會自動使用 yyyymmdd_HHMMSS.csv，背景工作每 5 秒新增 1 行假資料。</p>

        <div class="spa-chat-form" style="margin-top: 1rem; gap: 0.5rem; display: grid">
            <p class="spa-chat-hint">
                Queue 狀態（{{ queueStats.queue }}）：
                Ready {{ queueStats.messages_ready }}
                / Unacked {{ queueStats.messages_unacknowledged }}
                / Total {{ queueStats.messages_total }}
                / Consumers {{ queueStats.consumers }}
            </p>

            <el-progress
                :percentage="queueStats.drain_progress_percentage"
                :status="queueStats.messages_total === 0 ? 'success' : ''"
                :stroke-width="16"
            >
                <span>{{ queueStats.drain_progress_percentage }}%</span>
            </el-progress>
        </div>

        <div class="spa-chat-form" style="margin-top: 1rem; gap: 1rem; display: grid">
            <div>
                <p class="spa-chat-hint">選擇欄位</p>
                <el-checkbox-group v-model="selectedColumns">
                    <el-space wrap>
                        <el-checkbox
                            v-for="option in columnOptions"
                            :key="option.value"
                            :label="option.value"
                        >
                            {{ option.label }}
                        </el-checkbox>
                    </el-space>
                </el-checkbox-group>
            </div>

            <div class="spa-chat-actions">
                <el-input-number v-model="totalRows" :min="1" :max="120" />
                <span class="spa-chat-hint">總筆數</span>
                <el-button type="primary" :loading="isSubmitting" @click="createTask">建立匯出任務</el-button>
                <el-button plain @click="loadTasks">重新整理</el-button>
            </div>
        </div>

        <div class="spa-chat-history" style="margin-top: 1rem; min-height: 18rem; max-height: none">
            <article v-if="tasks.length === 0" class="spa-chat-message spa-chat-message-model">
                <div class="spa-chat-role">CSV Export</div>
                <p class="spa-chat-text">目前沒有匯出任務</p>
            </article>

            <article v-for="task in tasks" :key="task.id" class="spa-chat-message spa-chat-message-model">
                <div class="spa-chat-role">{{ task.file_name }}</div>
                <p class="spa-chat-text">狀態：{{ renderStatus(task.status) }}</p>
                <p class="spa-chat-text">進度：{{ task.generated_rows }} / {{ task.total_rows }}</p>
                <el-progress
                    :percentage="task.progress_percentage ?? calculateProgress(task)"
                    :status="progressStatus(task.status)"
                    :stroke-width="14"
                    style="max-width: 32rem; margin-top: 0.35rem"
                />
                <p class="spa-chat-text">欄位：{{ task.columns.join(', ') }}</p>
                <p v-if="task.last_error" class="spa-chat-text">錯誤：{{ task.last_error }}</p>

                <el-space wrap style="margin-top: 0.5rem">
                    <el-button plain size="small" @click="loadTask(task.id)">刷新狀態</el-button>
                    <el-button
                        plain
                        size="small"
                        :disabled="task.generated_rows === 0"
                        @click="downloadTask(task)"
                    >
                        下載 CSV
                    </el-button>
                </el-space>
            </article>
        </div>
    </el-card>
</template>

<script setup>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { useRouter } from 'vue-router';

const router = useRouter();

const selectedColumns = ref(['serial_no', 'name', 'email']);
const totalRows = ref(5);
const columnOptions = ref([]);
const tasks = ref([]);
const queueStats = ref({
    queue: 'default',
    messages_ready: 0,
    messages_unacknowledged: 0,
    messages_total: 0,
    consumers: 0,
    drain_progress_percentage: 100,
});
const isSubmitting = ref(false);
let refreshTimer = null;

const goAdminHome = () => {
    router.push('/admin');
};

const normalizeColumnOptions = (availableColumns) => {
    return Object.entries(availableColumns ?? {}).map(([value, label]) => ({ value, label }));
};

const loadTasks = async () => {
    try {
        const response = await axios.get('/api/admin/csv-exports');
        columnOptions.value = normalizeColumnOptions(response?.data?.data?.available_columns);
        tasks.value = response?.data?.data?.items ?? [];
    } catch (error) {
        const errorMessage = error?.response?.data?.error ?? '載入匯出任務失敗';
        ElMessage.error(errorMessage);
    }
};

const loadQueueStats = async (silent = false) => {
    try {
        const response = await axios.get('/api/admin/queue/stats', {
            params: {
                queue: 'default',
            },
        });

        queueStats.value = response?.data?.data ?? queueStats.value;
    } catch (error) {
        if (!silent) {
            const errorMessage = error?.response?.data?.error ?? '載入 Queue 狀態失敗';
            ElMessage.error(errorMessage);
        }
    }
};

const loadTask = async (taskId) => {
    try {
        const response = await axios.get(`/api/admin/csv-exports/${taskId}`);
        const currentTask = response?.data?.data;

        tasks.value = tasks.value.map((task) => {
            return task.id === taskId ? currentTask : task;
        });
    } catch (error) {
        const errorMessage = error?.response?.data?.error ?? '載入任務狀態失敗';
        ElMessage.error(errorMessage);
    }
};

const createTask = async () => {
    if (selectedColumns.value.length === 0) {
        ElMessage.error('請至少選擇一個欄位');
        return;
    }

    isSubmitting.value = true;

    try {
        await axios.post('/api/admin/csv-exports', {
            columns: selectedColumns.value,
            total_rows: totalRows.value,
        });

        ElMessage.success('CSV 匯出任務已建立');
        await Promise.all([loadTasks(), loadQueueStats(true)]);
    } catch (error) {
        const errorMessage = error?.response?.data?.error ?? '建立匯出任務失敗';
        ElMessage.error(errorMessage);
    } finally {
        isSubmitting.value = false;
    }
};

const downloadTask = async (task) => {
    try {
        const response = await axios.get(`/api/admin/csv-exports/${task.id}/download`, {
            responseType: 'blob',
        });

        const blobUrl = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = blobUrl;
        link.setAttribute('download', task.file_name);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(blobUrl);
    } catch (error) {
        const errorMessage = error?.response?.data?.error ?? '下載 CSV 失敗';
        ElMessage.error(errorMessage);
    }
};

const renderStatus = (status) => {
    const map = {
        pending: '等待中',
        processing: '處理中',
        completed: '已完成',
        failed: '失敗',
    };

    return map[status] ?? status;
};

const progressStatus = (status) => {
    if (status === 'completed') {
        return 'success';
    }

    if (status === 'failed') {
        return 'exception';
    }

    return '';
};

const calculateProgress = (task) => {
    const totalRows = Math.max(Number(task?.total_rows ?? 0), 1);
    const generatedRows = Math.max(Number(task?.generated_rows ?? 0), 0);

    return Math.min(100, Math.floor((generatedRows / totalRows) * 100));
};

const startPolling = () => {
    refreshTimer = window.setInterval(() => {
        const hasActiveTask = tasks.value.some((task) => ['pending', 'processing'].includes(task.status));

        if (hasActiveTask) {
            loadTasks();
        }

        loadQueueStats(true);
    }, 5000);
};

onMounted(async () => {
    await Promise.all([loadTasks(), loadQueueStats()]);
    startPolling();
});

onBeforeUnmount(() => {
    if (refreshTimer) {
        window.clearInterval(refreshTimer);
    }
});
</script>