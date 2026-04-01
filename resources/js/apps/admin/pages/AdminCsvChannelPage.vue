<template>
    <div class="csv-channel-page">
        <el-card class="spa-panel csv-channel-hero" shadow="hover">
            <template #header>
                <div class="spa-panel-header spa-chat-header">
                    <div>
                        <el-tag type="success">InfluxDB / Channel</el-tag>
                        <span class="spa-panel-title">Channel 管理</span>
                    </div>

                    <el-space wrap>
                        <el-button plain @click="goCsvExportPage">前往 CSV 匯出</el-button>
                        <el-button plain @click="goAdminHome">回後台首頁</el-button>
                    </el-space>
                </div>
            </template>

            <div class="csv-channel-hero__content">
                <div>
                    <p class="spa-description">
                        這裡用來定義 InfluxDB measurement、timestamp source、tags 與 fields。建立後可提供後續 CSV 匯入流程綁定使用。
                    </p>
                </div>

                <div class="csv-channel-stats">
                    <article class="csv-channel-stat-card">
                        <span class="csv-channel-stat-card__label">Channel</span>
                        <strong class="csv-channel-stat-card__value">{{ channels.length }}</strong>
                    </article>

                    <article class="csv-channel-stat-card">
                        <span class="csv-channel-stat-card__label">啟用中</span>
                        <strong class="csv-channel-stat-card__value">{{ activeChannelCount }}</strong>
                    </article>

                    <article class="csv-channel-stat-card">
                        <span class="csv-channel-stat-card__label">Measurement</span>
                        <strong class="csv-channel-stat-card__value">{{ measurementCount }}</strong>
                    </article>
                </div>
            </div>
        </el-card>

        <section class="csv-channel-grid">
            <el-card class="spa-panel csv-channel-list-panel" shadow="hover">
                <template #header>
                    <div class="spa-panel-header spa-chat-header">
                        <div>
                            <el-tag type="info">Library</el-tag>
                            <span class="spa-panel-title">Channel 清單</span>
                        </div>

                        <el-space wrap>
                            <el-button plain @click="loadChannels">重新整理</el-button>
                            <el-button type="primary" @click="startCreate">新增 Channel</el-button>
                        </el-space>
                    </div>
                </template>

                <div class="csv-channel-toolbar">
                    <el-input
                        v-model="keyword"
                        clearable
                        placeholder="搜尋代碼、名稱或 measurement"
                    />

                    <el-segmented
                        v-model="statusFilter"
                        :options="statusOptions"
                    />
                </div>

                <div class="csv-channel-list">
                    <article
                        v-for="channel in filteredChannels"
                        :key="channel.id"
                        class="csv-channel-item"
                        :class="{
                            'csv-channel-item--active': Number(selectedChannelId) === Number(channel.id),
                        }"
                        @click="selectChannel(channel)"
                    >
                        <div class="csv-channel-item__head">
                            <div>
                                <h3 class="csv-channel-item__title">{{ channel.name }}</h3>
                                <p class="csv-channel-item__code">{{ channel.code }}</p>
                                <p class="csv-channel-item__measurement">{{ channel.measurement }}</p>
                            </div>

                            <el-tag :type="channel.is_active ? 'success' : 'info'" effect="light">
                                {{ channel.is_active ? '啟用中' : '停用' }}
                            </el-tag>
                        </div>

                        <div class="csv-channel-item__meta">
                            <span>Code: {{ channel.code }}</span>
                            <span>Timestamp: {{ channel.timestamp_source }}</span>
                            <span>Tags: {{ channel.tags.length }}</span>
                            <span>Fields: {{ channel.fields.length }}</span>
                        </div>

                        <p class="csv-channel-item__time">更新時間：{{ formatDateTime(channel.updated_at) }}</p>
                    </article>

                    <article v-if="filteredChannels.length === 0" class="csv-channel-empty">
                        <div class="spa-chat-role">Channel</div>
                        <p class="spa-chat-text">目前沒有符合條件的 channel。</p>
                    </article>
                </div>
            </el-card>

            <el-card class="spa-panel csv-channel-editor-panel" shadow="hover">
                <template #header>
                    <div class="spa-panel-header spa-chat-header">
                        <div>
                            <el-tag :type="isEditing ? 'warning' : 'primary'">
                                {{ isEditing ? 'Edit' : 'Create' }}
                            </el-tag>
                            <span class="spa-panel-title">{{ isEditing ? '編輯 Channel' : '建立 Channel' }}</span>
                        </div>

                        <el-space wrap>
                            <el-button plain @click="resetForm">重設</el-button>
                            <el-button type="primary" :loading="isSaving" @click="submitChannel">
                                {{ isEditing ? '儲存修改' : '建立 Channel' }}
                            </el-button>
                        </el-space>
                    </div>
                </template>

                <el-form label-position="top" class="csv-channel-form">
                    <div class="csv-channel-form__grid">
                        <el-form-item label="Channel 代碼" required>
                            <el-input v-model="form.code" placeholder="例如 task_progress_default" />
                        </el-form-item>

                        <el-form-item label="Channel 名稱" required>
                            <el-input v-model="form.name" placeholder="例如 task-progress-default" />
                        </el-form-item>

                        <el-form-item label="Measurement" required>
                            <el-input v-model="form.measurement" placeholder="例如 csv_export_task_progress" />
                        </el-form-item>
                    </div>

                    <div class="csv-channel-form__grid csv-channel-form__grid--compact">
                        <el-form-item label="Timestamp Source">
                            <el-select v-model="form.timestamp_source" class="csv-channel-form__full-width">
                                <el-option
                                    v-for="option in timestampSourceOptions"
                                    :key="option.value"
                                    :label="option.label"
                                    :value="option.value"
                                />
                            </el-select>
                        </el-form-item>

                        <el-form-item label="狀態">
                            <div class="csv-channel-status-switch">
                                <el-switch v-model="form.is_active" />
                                <span class="csv-channel-status-switch__label">
                                    {{ form.is_active ? '啟用中' : '停用' }}
                                </span>
                            </div>
                        </el-form-item>
                    </div>

                    <section class="csv-channel-section">
                        <div class="csv-channel-section__head">
                            <div>
                                <p class="csv-channel-section__title">Tags</p>
                                <p class="csv-channel-section__hint">選擇要作為 Influx tag 的 CSV 欄位名稱。</p>
                            </div>

                            <el-button plain @click="addTag">新增 Tag</el-button>
                        </div>

                        <div v-if="form.tags.length === 0" class="csv-channel-row-empty">
                            尚未設定 tag。
                        </div>

                        <div v-for="(tag, index) in form.tags" :key="tag.uid" class="csv-channel-row-stack">
                            <div class="csv-channel-row-grid">
                                <el-select
                                    v-model="tag.column_name"
                                    filterable
                                    allow-create
                                    default-first-option
                                    clearable
                                    no-data-text="目前沒有預設欄位，可直接輸入"
                                    placeholder="選擇 CSV 欄位名稱，例如 status"
                                >
                                    <el-option
                                        v-for="option in tagColumnOptions"
                                        :key="option.value"
                                        :label="option.label"
                                        :value="option.value"
                                    />
                                </el-select>
                                <el-input-number v-model="tag.sort_order" :min="0" :step="1" />
                                <el-button plain type="danger" @click="removeTag(index)">移除</el-button>
                            </div>

                            <el-select
                                v-model="tag.allowed_values"
                                multiple
                                filterable
                                allow-create
                                default-first-option
                                clearable
                                collapse-tags
                                no-data-text="輸入後按 Enter 新增限定值"
                                placeholder="可選：一次輸入一個值並按 Enter，例如 queued、done"
                            />
                        </div>
                    </section>

                    <section class="csv-channel-section">
                        <div class="csv-channel-section__head">
                            <div>
                                <p class="csv-channel-section__title">Fields</p>
                                <p class="csv-channel-section__hint">選擇要作為 Influx field 的 CSV 欄位名稱，並定義資料型別。</p>
                            </div>

                            <el-button plain @click="addField">新增 Field</el-button>
                        </div>

                        <div v-if="form.fields.length === 0" class="csv-channel-row-empty">
                            尚未設定 field。
                        </div>

                        <div v-for="(field, index) in form.fields" :key="field.uid" class="csv-channel-row-grid csv-channel-row-grid--field">
                            <el-select
                                v-model="field.column_name"
                                filterable
                                allow-create
                                default-first-option
                                clearable
                                no-data-text="目前沒有預設欄位，可直接輸入"
                                placeholder="選擇 CSV 欄位名稱，例如 serial_no"
                            >
                                <el-option
                                    v-for="option in fieldColumnOptions"
                                    :key="option.value"
                                    :label="option.label"
                                    :value="option.value"
                                />
                            </el-select>

                            <el-select v-model="field.data_type">
                                <el-option
                                    v-for="option in dataTypeOptions"
                                    :key="option.value"
                                    :label="option.label"
                                    :value="option.value"
                                />
                            </el-select>

                            <el-input-number v-model="field.sort_order" :min="0" :step="1" />
                            <el-button plain type="danger" @click="removeField(index)">移除</el-button>
                        </div>
                    </section>

                    <div v-if="isEditing" class="csv-channel-danger-zone">
                        <div>
                            <p class="csv-channel-section__title">Danger Zone</p>
                            <p class="csv-channel-section__hint">刪除後，這個 channel 會從目前帳號下移除。</p>
                        </div>

                        <el-button type="danger" plain :loading="isDeleting" @click="deleteChannel">
                            刪除 Channel
                        </el-button>
                    </div>
                </el-form>
            </el-card>
        </section>
    </div>
</template>

<script setup>
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useRouter } from 'vue-router';

const router = useRouter();

const channels = ref([]);
const columnOptions = ref([]);
const tagColumnOptions = ref([]);
const fieldColumnOptions = ref([]);
const keyword = ref('');
const statusFilter = ref('all');
const isSaving = ref(false);
const isDeleting = ref(false);
const selectedChannelId = ref(null);
const createModeLocked = ref(false);
const form = ref(buildEmptyForm());

const statusOptions = [
    {
        label: '全部',
        value: 'all',
    },
    {
        label: '啟用',
        value: 'active',
    },
    {
        label: '停用',
        value: 'inactive',
    },
];

const timestampSourceOptions = [
    {
        label: '現在時間',
        value: 'now',
    },
    {
        label: 'task_started_at',
        value: 'task_started_at',
    },
    {
        label: 'task_finished_at',
        value: 'task_finished_at',
    },
    {
        label: 'task_updated_at',
        value: 'task_updated_at',
    },
];

const dataTypeOptions = [
    {
        label: 'Int',
        value: 'int',
    },
    {
        label: 'Float',
        value: 'float',
    },
    {
        label: 'Bool',
        value: 'bool',
    },
    {
        label: 'String',
        value: 'string',
    },
];

const filteredChannels = computed(() => {
    return channels.value.filter((channel) => {
        const normalizedKeyword = keyword.value.trim().toLowerCase();
        const matchesKeyword = normalizedKeyword === ''
            || String(channel.code ?? '').toLowerCase().includes(normalizedKeyword)
            || String(channel.name ?? '').toLowerCase().includes(normalizedKeyword)
            || String(channel.measurement ?? '').toLowerCase().includes(normalizedKeyword);

        const matchesStatus = statusFilter.value === 'all'
            || (statusFilter.value === 'active' && channel.is_active)
            || (statusFilter.value === 'inactive' && !channel.is_active);

        return matchesKeyword && matchesStatus;
    });
});

const isEditing = computed(() => {
    const id = selectedChannelId.value;

    if (id === null || id === undefined || id === '') {
        return false;
    }

    const parsed = Number(id);

    return Number.isInteger(parsed) && parsed > 0;
});

const activeChannelCount = computed(() => {
    return channels.value.filter((channel) => channel.is_active).length;
});

const measurementCount = computed(() => {
    return new Set(channels.value.map((channel) => channel.measurement)).size;
});

function normalizeColumnOptions(availableColumns) {
    return Object.entries(availableColumns ?? {}).map(([value, label]) => ({ value, label }));
}

async function loadAvailableColumnOptions() {
    const channelResponse = await axios.get('/api/admin/csv-channels');
    const channelAvailableColumns = channelResponse?.data?.data?.available_columns ?? {};
    const channelAvailableTagColumns = channelResponse?.data?.data?.available_tag_columns ?? {};
    const channelAvailableFieldColumns = channelResponse?.data?.data?.available_field_columns ?? {};
    const channelItems = channelResponse?.data?.data?.items ?? [];

    let availableColumns = channelAvailableColumns;
    let availableTagColumns = channelAvailableTagColumns;
    let availableFieldColumns = channelAvailableFieldColumns;

    if (Object.keys(availableColumns).length === 0) {
        const exportResponse = await axios.get('/api/admin/csv-exports');
        availableColumns = exportResponse?.data?.data?.available_columns ?? {};
        availableTagColumns = exportResponse?.data?.data?.available_tag_columns ?? availableColumns;
        availableFieldColumns = exportResponse?.data?.data?.available_field_columns ?? availableColumns;
    }

    if (Object.keys(availableTagColumns).length === 0) {
        availableTagColumns = availableColumns;
    }

    if (Object.keys(availableFieldColumns).length === 0) {
        availableFieldColumns = availableColumns;
    }

    return {
        items: channelItems,
        options: normalizeColumnOptions(availableColumns),
        tagOptions: normalizeColumnOptions(availableTagColumns),
        fieldOptions: normalizeColumnOptions(availableFieldColumns),
    };
}

function buildEmptyForm() {
    return {
        id: null,
        code: '',
        name: '',
        measurement: '',
        timestamp_source: 'now',
        is_active: true,
        tags: [buildTagRow(0)],
        fields: [buildFieldRow(0)],
    };
}

function buildTagRow(sortOrder = 0) {
    return {
        uid: crypto.randomUUID(),
        column_name: '',
        allowed_values: [],
        sort_order: sortOrder,
    };
}

function buildFieldRow(sortOrder = 0) {
    return {
        uid: crypto.randomUUID(),
        column_name: '',
        data_type: 'string',
        sort_order: sortOrder,
    };
}

function normalizeChannel(channel) {
    return {
        ...channel,
        is_active: Boolean(channel.is_active),
        tags: Array.isArray(channel.tags) ? channel.tags : [],
        fields: Array.isArray(channel.fields) ? channel.fields : [],
    };
}

function cloneChannelToForm(channel) {
    return {
        id: channel.id,
        code: channel.code ?? '',
        name: channel.name ?? '',
        measurement: channel.measurement ?? '',
        timestamp_source: channel.timestamp_source ?? 'now',
        is_active: Boolean(channel.is_active),
        tags: (channel.tags ?? []).map((tag, index) => ({
            uid: crypto.randomUUID(),
            column_name: tag.column_name ?? '',
            allowed_values: Array.isArray(tag.allowed_values) ? tag.allowed_values : [],
            sort_order: Number(tag.sort_order ?? index),
        })),
        fields: (channel.fields ?? []).map((field, index) => ({
            uid: crypto.randomUUID(),
            column_name: field.column_name ?? '',
            data_type: field.data_type ?? 'string',
            sort_order: Number(field.sort_order ?? index),
        })),
    };
}

function formatDateTime(value) {
    if (!value) {
        return '尚未更新';
    }

    const parsed = new Date(value);
    if (Number.isNaN(parsed.getTime())) {
        return value;
    }

    return parsed.toLocaleString('zh-TW', {
        hour12: false,
    });
}

function goAdminHome() {
    router.push('/admin');
}

function goCsvExportPage() {
    router.push('/admin/exports/csv');
}

function resetForm() {
    if (isEditing.value) {
        const currentChannel = channels.value.find((channel) => Number(channel.id) === Number(selectedChannelId.value));

        if (currentChannel) {
            form.value = cloneChannelToForm(currentChannel);
            return;
        }
    }

    selectedChannelId.value = null;
    form.value = buildEmptyForm();
}

function startCreate() {
    createModeLocked.value = true;
    selectedChannelId.value = null;
    form.value = buildEmptyForm();
}

function selectChannel(channel) {
    createModeLocked.value = false;
    selectedChannelId.value = channel.id;
    form.value = cloneChannelToForm(channel);

    if (form.value.tags.length === 0) {
        form.value.tags.push(buildTagRow(0));
    }

    if (form.value.fields.length === 0) {
        form.value.fields.push(buildFieldRow(0));
    }
}

function addTag() {
    form.value.tags.push(buildTagRow(form.value.tags.length));
}

function removeTag(index) {
    form.value.tags.splice(index, 1);

    if (form.value.tags.length === 0) {
        form.value.tags.push(buildTagRow(0));
    }
}

function addField() {
    form.value.fields.push(buildFieldRow(form.value.fields.length));
}

function removeField(index) {
    form.value.fields.splice(index, 1);

    if (form.value.fields.length === 0) {
        form.value.fields.push(buildFieldRow(0));
    }
}

function buildPayload() {
    return {
        code: form.value.code.trim(),
        name: form.value.name.trim(),
        measurement: form.value.measurement.trim(),
        timestamp_source: form.value.timestamp_source,
        is_active: form.value.is_active,
        tags: form.value.tags
            .filter((tag) => tag.column_name.trim() !== '')
            .map((tag, index) => ({
                column_name: tag.column_name.trim(),
                allowed_values: Array.from(new Set((tag.allowed_values ?? []).map((value) => String(value).trim()).filter((value) => value !== ''))),
                sort_order: Number(tag.sort_order ?? index),
            })),
        fields: form.value.fields
            .filter((field) => field.column_name.trim() !== '')
            .map((field, index) => ({
                column_name: field.column_name.trim(),
                data_type: field.data_type,
                sort_order: Number(field.sort_order ?? index),
            })),
    };
}

async function loadChannels() {
    try {
        const { items, options, tagOptions, fieldOptions } = await loadAvailableColumnOptions();

        columnOptions.value = options;
        tagColumnOptions.value = tagOptions;
        fieldColumnOptions.value = fieldOptions;
        channels.value = items.map((channel) => normalizeChannel(channel));

        if (selectedChannelId.value) {
            const currentChannel = channels.value.find((channel) => Number(channel.id) === Number(selectedChannelId.value));

            if (currentChannel) {
                form.value = cloneChannelToForm(currentChannel);
                return;
            }
        }

        if (channels.value.length > 0 && !isEditing.value && !createModeLocked.value) {
            selectChannel(channels.value[0]);
            return;
        }

        if (channels.value.length === 0) {
            startCreate();
        }
    } catch (error) {
        const errorMessage = error?.response?.data?.message ?? error?.response?.data?.error ?? '載入 channel 失敗';
        ElMessage.error(errorMessage);
    }
}

async function submitChannel() {
    if (form.value.code.trim() === '' || form.value.name.trim() === '' || form.value.measurement.trim() === '') {
        ElMessage.error('請填寫 channel 代碼、名稱與 measurement');
        return;
    }

    isSaving.value = true;

    try {
        const payload = buildPayload();
        let response = null;

        if (isEditing.value) {
            response = await axios.patch(`/api/admin/csv-channels/${selectedChannelId.value}`, payload);
            ElMessage.success('Channel 已更新');
        } else {
            response = await axios.post('/api/admin/csv-channels', payload);
            ElMessage.success('Channel 已建立');
            createModeLocked.value = false;
        }

        const savedChannel = normalizeChannel(response?.data?.data ?? {});
        await loadChannels();

        if (savedChannel?.id) {
            const matchedChannel = channels.value.find((channel) => Number(channel.id) === Number(savedChannel.id));

            if (matchedChannel) {
                selectChannel(matchedChannel);
            }
        }
    } catch (error) {
        const errorMessage = error?.response?.data?.message ?? error?.response?.data?.error ?? '儲存 channel 失敗';
        ElMessage.error(errorMessage);
    } finally {
        isSaving.value = false;
    }
}

async function deleteChannel() {
    if (!isEditing.value) {
        return;
    }

    try {
        await ElMessageBox.confirm(`確定要刪除 ${form.value.name} 嗎？`, '刪除確認', {
            confirmButtonText: '刪除',
            cancelButtonText: '取消',
            type: 'warning',
        });
    } catch {
        return;
    }

    isDeleting.value = true;

    try {
        await axios.delete(`/api/admin/csv-channels/${selectedChannelId.value}`);
        ElMessage.success('Channel 已刪除');
        startCreate();
        createModeLocked.value = false;
        await loadChannels();
    } catch (error) {
        const errorMessage = error?.response?.data?.message ?? error?.response?.data?.error ?? '刪除 channel 失敗';
        ElMessage.error(errorMessage);
    } finally {
        isDeleting.value = false;
    }
}

onMounted(async () => {
    await loadChannels();
});
</script>

<style scoped>
.csv-channel-page {
    display: grid;
    gap: 1rem;
}

.csv-channel-hero {
    overflow: hidden;
    background:
        radial-gradient(circle at top right, rgba(34, 197, 94, 0.18), transparent 28%),
        linear-gradient(135deg, rgba(15, 23, 42, 0.92), rgba(3, 105, 161, 0.2));
}

.csv-channel-hero__content {
    display: grid;
    gap: 1.5rem;
}

.csv-channel-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 0.85rem;
}

.csv-channel-stat-card {
    padding: 1rem;
    border-radius: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.18);
    background: rgba(2, 6, 23, 0.46);
}

.csv-channel-stat-card__label {
    display: block;
    color: #94a3b8;
    font-size: 0.82rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.csv-channel-stat-card__value {
    display: block;
    margin-top: 0.5rem;
    color: #f8fafc;
    font-size: clamp(1.5rem, 3vw, 2rem);
}

.csv-channel-grid {
    display: grid;
    grid-template-columns: minmax(20rem, 28rem) minmax(0, 1fr);
    gap: 1rem;
    align-items: start;
}

.csv-channel-list-panel,
.csv-channel-editor-panel {
    min-height: 40rem;
}

.csv-channel-toolbar {
    display: grid;
    gap: 0.85rem;
}

.csv-channel-list {
    display: grid;
    gap: 0.85rem;
    margin-top: 1rem;
}

.csv-channel-row-stack {
    display: grid;
    gap: 0.75rem;
}

.csv-channel-item {
    padding: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.16);
    border-radius: 1rem;
    background: rgba(15, 23, 42, 0.88);
    cursor: pointer;
    transition: border-color 0.2s ease, transform 0.2s ease, background-color 0.2s ease;
}

.csv-channel-item:hover {
    transform: translateY(-1px);
    border-color: rgba(56, 189, 248, 0.52);
}

.csv-channel-item--active {
    border-color: rgba(34, 197, 94, 0.56);
    background: linear-gradient(135deg, rgba(8, 47, 73, 0.82), rgba(15, 23, 42, 0.96));
}

.csv-channel-item__head {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
}

.csv-channel-item__title {
    margin: 0;
    color: #f8fafc;
    font-size: 1.05rem;
}

.csv-channel-item__code {
    margin: 0.35rem 0 0;
    color: #86efac;
    font-size: 0.82rem;
    letter-spacing: 0.04em;
    text-transform: uppercase;
}

.csv-channel-item__measurement {
    margin: 0.25rem 0 0;
    color: #7dd3fc;
    font-size: 0.92rem;
}

.csv-channel-item__meta {
    display: flex;
    flex-wrap: wrap;
    gap: 0.75rem;
    margin-top: 0.9rem;
    color: #cbd5e1;
    font-size: 0.9rem;
}

.csv-channel-item__time {
    margin: 0.85rem 0 0;
    color: #94a3b8;
    font-size: 0.82rem;
}

.csv-channel-empty {
    padding: 1rem 1.1rem;
    border-radius: 1rem;
    border: 1px dashed rgba(148, 163, 184, 0.24);
    background: rgba(15, 23, 42, 0.45);
}

.csv-channel-form {
    display: grid;
    gap: 1.25rem;
}

.csv-channel-form__grid {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 1rem;
}

.csv-channel-form__grid--compact {
    grid-template-columns: minmax(0, 18rem) minmax(0, 1fr);
}

.csv-channel-form__full-width {
    width: 100%;
}

.csv-channel-status-switch {
    display: inline-flex;
    align-items: center;
    gap: 0.75rem;
    min-height: 2rem;
}

.csv-channel-status-switch__label {
    color: #e2e8f0;
}

.csv-channel-section {
    display: grid;
    gap: 0.85rem;
    padding: 1rem;
    border-radius: 1rem;
    border: 1px solid rgba(148, 163, 184, 0.16);
    background: rgba(2, 6, 23, 0.3);
}

.csv-channel-section__head {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: flex-start;
}

.csv-channel-section__title {
    margin: 0;
    color: #f8fafc;
    font-size: 1rem;
    font-weight: 600;
}

.csv-channel-section__hint {
    margin: 0.3rem 0 0;
    color: #94a3b8;
    line-height: 1.6;
}

.csv-channel-row-empty {
    padding: 0.9rem 1rem;
    border-radius: 0.85rem;
    background: rgba(15, 23, 42, 0.6);
    color: #94a3b8;
}

.csv-channel-row-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) 8rem 6rem;
    gap: 0.75rem;
    align-items: center;
}

.csv-channel-row-grid--field {
    grid-template-columns: minmax(0, 1.2fr) minmax(0, 0.8fr) 8rem 6rem;
}

.csv-channel-danger-zone {
    display: flex;
    justify-content: space-between;
    gap: 1rem;
    align-items: center;
    padding: 1rem;
    border-radius: 1rem;
    border: 1px solid rgba(248, 113, 113, 0.28);
    background: rgba(127, 29, 29, 0.18);
}

@media (max-width: 1200px) {
    .csv-channel-grid {
        grid-template-columns: 1fr;
    }

    .csv-channel-form__grid {
        grid-template-columns: 1fr;
    }

    .csv-channel-row-grid,
    .csv-channel-row-grid--field {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }
}

@media (max-width: 768px) {
    .csv-channel-stats,
    .csv-channel-form__grid,
    .csv-channel-form__grid--compact,
    .csv-channel-row-grid,
    .csv-channel-row-grid--field {
        grid-template-columns: 1fr;
    }

    .csv-channel-section__head,
    .csv-channel-danger-zone {
        flex-direction: column;
        align-items: stretch;
    }
}
</style>