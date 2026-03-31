<template>
    <el-card class="spa-panel spa-chat-panel" shadow="hover">
        <template #header>
            <div class="spa-panel-header spa-chat-header">
                <div>
                    <el-tag type="warning">Queue / RabbitMQ</el-tag>
                    <span class="spa-panel-title">CSV 匯出</span>
                </div>
                <el-space wrap>
                    <el-button plain @click="goCsvChannelPage">Channel 管理</el-button>
                    <el-button plain @click="goAdminHome">回後台首頁</el-button>
                </el-space>
            </div>
        </template>

        <p class="spa-description">未選 channel 時檔名會使用 yyyymmdd_HHMMSS.csv；若有選 channel，檔名會額外帶入 channel code 與 measurement。</p>

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
                <p class="spa-chat-hint">選擇 Channel</p>
                <el-select v-model="selectedChannelId" clearable placeholder="不指定 Channel，改用手動欄位" style="max-width: 28rem; width: 100%">
                    <el-option
                        v-for="channel in channels"
                        :key="channel.id"
                        :label="`${channel.name} (${channel.code})`"
                        :value="channel.id"
                    >
                        <div style="display: flex; justify-content: space-between; gap: 1rem">
                            <span>{{ channel.name }}</span>
                            <span style="color: var(--el-text-color-secondary)">{{ channel.measurement }}</span>
                        </div>
                    </el-option>
                </el-select>

                <p v-if="selectedChannel" class="spa-chat-hint" style="margin-top: 0.5rem">
                    使用欄位：{{ selectedChannel.columns.join(', ') }}
                </p>
            </div>

            <div>
                <p class="spa-chat-hint">選擇欄位</p>
                <el-checkbox-group v-model="selectedColumns" :disabled="Boolean(selectedChannel)">
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
                <p v-if="task.channel_code" class="spa-chat-text">Channel：{{ task.channel_code }} / {{ task.measurement }}</p>
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
import { getApps, initializeApp } from 'firebase/app';
import { doc, getFirestore, onSnapshot } from 'firebase/firestore';
import { useRouter } from 'vue-router';

const router = useRouter();

const selectedColumns = ref(['serial_no', 'name', 'email']);
const selectedChannelId = ref(null);
const totalRows = ref(5);
const columnOptions = ref([]);
const channels = ref([]);
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
let firestoreDb = null;
const taskUnsubscribers = new Map();
let hasRealtimeErrorNotified = false;

const goAdminHome = () => {
    router.push('/admin');
};

const goCsvChannelPage = () => {
    router.push('/admin/exports/csv/channels');
};

const selectedChannel = computed(() => {
    return channels.value.find((channel) => Number(channel.id) === Number(selectedChannelId.value)) ?? null;
});

const normalizeColumnOptions = (availableColumns) => {
    return Object.entries(availableColumns ?? {}).map(([value, label]) => ({ value, label }));
};

const normalizeRealtimeTask = (snapshot) => {
    const decoded = snapshot.data() ?? {};

    const taskId = Number(decoded.task_id ?? snapshot.id);
    if (!Number.isFinite(taskId)) {
        return null;
    }

    return {
        ...decoded,
        id: taskId,
        columns: Array.isArray(decoded.columns) ? decoded.columns : [],
        generated_rows: Number(decoded.generated_rows ?? 0),
        total_rows: Number(decoded.total_rows ?? 0),
        progress_percentage: Number(decoded.progress_percentage ?? 0),
    };
};

const upsertTask = (nextTask) => {
    const index = tasks.value.findIndex((task) => Number(task.id) === Number(nextTask.id));

    if (index === -1) {
        return;
    }

    const merged = {
        ...tasks.value[index],
        ...nextTask,
    };

    tasks.value.splice(index, 1, merged);
};

const disableRealtimeSync = (message) => {
    for (const unsubscribe of taskUnsubscribers.values()) {
        unsubscribe();
    }

    taskUnsubscribers.clear();
    firestoreDb = null;

    if (!hasRealtimeErrorNotified) {
        ElMessage.warning(message ?? 'Firebase 即時監聽失敗，改用輪詢更新。');
        hasRealtimeErrorNotified = true;
    }
};

const attachTaskListener = (taskId) => {
    if (!firestoreDb || taskUnsubscribers.has(taskId)) {
        return;
    }

    const collectionName = import.meta.env.VITE_FIRESTORE_TASK_COLLECTION || 'csv_export_tasks';
    const reference = doc(firestoreDb, collectionName, String(taskId));

    const unsubscribe = onSnapshot(
        reference,
        (snapshot) => {
            if (!snapshot.exists()) {
                return;
            }

            const nextTask = normalizeRealtimeTask(snapshot);
            if (!nextTask) {
                return;
            }

            upsertTask(nextTask);
        },
        (error) => {
            disableRealtimeSync(error?.message ?? 'Firebase 即時監聽失敗，改用輪詢更新。');
        },
    );

    taskUnsubscribers.set(taskId, unsubscribe);
};

const syncTaskListeners = () => {
    if (!firestoreDb) {
        return;
    }

    const activeTaskIds = new Set(
        tasks.value
            .filter((task) => ['pending', 'processing'].includes(task.status))
            .map((task) => String(task.id)),
    );

    for (const [taskId, unsubscribe] of taskUnsubscribers.entries()) {
        if (!activeTaskIds.has(taskId)) {
            unsubscribe();
            taskUnsubscribers.delete(taskId);
        }
    }

    for (const taskId of activeTaskIds) {
        attachTaskListener(taskId);
    }
};

const initializeRealtimeSync = () => {
    const firebaseConfig = {
        apiKey: import.meta.env.VITE_FIREBASE_API_KEY,
        authDomain: import.meta.env.VITE_FIREBASE_AUTH_DOMAIN,
        projectId: import.meta.env.VITE_FIREBASE_PROJECT_ID,
        storageBucket: import.meta.env.VITE_FIREBASE_STORAGE_BUCKET,
        appId: import.meta.env.VITE_FIREBASE_APP_ID,
        messagingSenderId: import.meta.env.VITE_FIREBASE_MESSAGING_SENDER_ID,
    };

    const requiredKeys = ['apiKey', 'authDomain', 'projectId', 'appId'];
    const hasRequiredConfig = requiredKeys.every((key) => typeof firebaseConfig[key] === 'string' && firebaseConfig[key] !== '');

    if (!hasRequiredConfig) {
        return false;
    }

    try {
        const app = getApps()[0] ?? initializeApp(firebaseConfig);
        const databaseId = import.meta.env.VITE_FIREBASE_DATABASE_ID;
        firestoreDb = databaseId ? getFirestore(app, databaseId) : getFirestore(app);
        hasRealtimeErrorNotified = false;
        syncTaskListeners();

        return true;
    } catch (error) {
        ElMessage.warning(error?.message ?? 'Firebase 初始化失敗，改用輪詢更新。');

        return false;
    }
};

const loadTasks = async () => {
    try {
        const response = await axios.get('/api/admin/csv-exports');
        columnOptions.value = normalizeColumnOptions(response?.data?.data?.available_columns);
        channels.value = response?.data?.data?.channels ?? [];
        tasks.value = response?.data?.data?.items ?? [];
        syncTaskListeners();
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
    if (!selectedChannel.value && selectedColumns.value.length === 0) {
        ElMessage.error('請至少選擇一個欄位');
        return;
    }

    isSubmitting.value = true;

    try {
        await axios.post('/api/admin/csv-exports', {
            channel_id: selectedChannel.value?.id ?? null,
            columns: selectedChannel.value ? undefined : selectedColumns.value,
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

        if (hasActiveTask && !firestoreDb) {
            loadTasks();
        }

        loadQueueStats(true);
    }, 5000);
};

onMounted(async () => {
    await Promise.all([loadTasks(), loadQueueStats()]);

    initializeRealtimeSync();

    startPolling();
});

onBeforeUnmount(() => {
    if (refreshTimer) {
        window.clearInterval(refreshTimer);
    }

    for (const unsubscribe of taskUnsubscribers.values()) {
        unsubscribe();
    }

    taskUnsubscribers.clear();
});
</script>