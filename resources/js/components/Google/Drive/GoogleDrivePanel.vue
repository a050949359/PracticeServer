<template>
    <el-card class="spa-panel spa-chat-panel" shadow="hover">
        <template #header>
            <div class="spa-panel-header spa-chat-header">
                <div>
                    <el-tag type="success">Google Drive</el-tag>
                    <span class="spa-panel-title">Drive 檔案管理</span>
                </div>
                <el-button plain @click="goAdminHome">回後台首頁</el-button>
            </div>
        </template>

        <div class="spa-chat-actions" style="margin-bottom: 1rem">
            <span class="spa-chat-hint">
                <template v-if="oauth.connected">
                    已連結 Google 帳號：{{ oauth.email || 'unknown' }}
                </template>
                <template v-else>
                    尚未連結 Google 帳號
                </template>
            </span>

            <el-space wrap>
                <el-button v-if="!oauth.connected" type="primary" :loading="isConnecting" @click="connectGoogleDrive">
                    連結 Google Drive
                </el-button>
                <el-button v-else plain type="danger" :loading="isDisconnecting" @click="disconnectGoogleDrive">
                    解除連結
                </el-button>
            </el-space>
        </div>

        <div class="spa-chat-form">
            <label class="spa-image-upload-label" for="drive-file-input">選擇上傳檔案</label>
            <input id="drive-file-input" class="spa-image-upload-input" type="file" @change="handleFileChange" />

            <p v-if="selectedFileName" class="spa-chat-hint">已選擇：{{ selectedFileName }}</p>

            <div class="spa-chat-actions">
                <el-input
                    v-model="customFileName"
                    placeholder="可選：自訂上傳檔名"
                    clearable
                    style="max-width: 24rem"
                />
                <el-button type="primary" :loading="isUploading" @click="submitUpload">上傳到 Drive</el-button>
            </div>
        </div>

        <div style="margin-top: 1.25rem">
            <el-input
                v-model="keyword"
                placeholder="搜尋檔名"
                clearable
                style="max-width: 24rem"
                @clear="fetchFiles(1)"
                @keyup.enter="fetchFiles(1)"
            >
                <template #append>
                    <el-button @click="fetchFiles(1)">搜尋</el-button>
                </template>
            </el-input>
        </div>

        <div class="spa-chat-history" style="margin-top: 1rem; min-height: 18rem; max-height: none">
            <article v-if="files.length === 0" class="spa-chat-message spa-chat-message-model">
                <div class="spa-chat-role">Google Drive</div>
                <p class="spa-chat-text">目前沒有檔案紀錄</p>
            </article>

            <article v-for="item in files" :key="item.file_id" class="spa-chat-message spa-chat-message-model">
                <div class="spa-chat-role">{{ item.file_name }}</div>
                <p class="spa-chat-text">類型：{{ item.mime_type || 'unknown' }}</p>
                <p class="spa-chat-text">大小：{{ formatFileSize(item.file_size) }}</p>

                <el-space wrap style="margin-top: 0.5rem">
                    <el-button plain size="small" @click="downloadFile(item.file_id)">下載</el-button>
                    <el-button
                        v-if="item.web_view_link"
                        plain
                        size="small"
                        @click="openDriveLink(item.web_view_link)"
                    >
                        開啟 Drive
                    </el-button>
                    <el-button type="danger" size="small" :loading="deletingId === item.file_id" @click="deleteFile(item)">
                        刪除
                    </el-button>
                </el-space>
            </article>
        </div>

        <div class="spa-chat-actions" style="margin-top: 1rem">
            <span class="spa-chat-hint">共 {{ pagination.total }} 筆</span>
            <el-pagination
                background
                layout="prev, pager, next"
                :page-size="pagination.per_page"
                :current-page="pagination.page"
                :total="pagination.total"
                @current-change="fetchFiles"
            />
        </div>
    </el-card>
</template>

<script setup>
import axios from 'axios';
import { ElMessage, ElMessageBox } from 'element-plus';
import { useRouter } from 'vue-router';

const router = useRouter();

const selectedFile = ref(null);
const selectedFileName = ref('');
const customFileName = ref('');
const keyword = ref('');
const files = ref([]);
const isUploading = ref(false);
const deletingId = ref('');
const isConnecting = ref(false);
const isDisconnecting = ref(false);
const oauth = ref({
    connected: false,
    email: null,
    expires_at: null,
});
const pagination = ref({
    page: 1,
    per_page: 10,
    total: 0,
    last_page: 1,
});

const goAdminHome = () => {
    router.push('/admin');
};

const handleFileChange = (event) => {
    const file = event?.target?.files?.[0] ?? null;
    selectedFile.value = file;
    selectedFileName.value = file?.name ?? '';
};

const submitUpload = async () => {
    if (!oauth.value.connected) {
        ElMessage.error('請先連結 Google Drive');
        return;
    }

    if (!selectedFile.value || isUploading.value) {
        ElMessage.error('請先選擇檔案');
        return;
    }

    const payload = new FormData();
    payload.append('file', selectedFile.value);

    if (customFileName.value.trim() !== '') {
        payload.append('file_name', customFileName.value.trim());
    }

    isUploading.value = true;

    try {
        await axios.post('/api/google/drive/upload', payload, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        ElMessage.success('上傳成功');
        selectedFile.value = null;
        selectedFileName.value = '';
        customFileName.value = '';
        await fetchFiles(1);
    } catch (error) {
        const errorMessage = error?.response?.data?.error ?? '上傳失敗';
        ElMessage.error(errorMessage);
    } finally {
        isUploading.value = false;
    }
};

const fetchFiles = async (page = 1) => {
    if (!oauth.value.connected) {
        files.value = [];
        pagination.value = {
            page: 1,
            per_page: 10,
            total: 0,
            last_page: 1,
        };

        return;
    }

    try {
        const response = await axios.get('/api/google/drive/files', {
            params: {
                page,
                per_page: pagination.value.per_page,
                keyword: keyword.value.trim() || undefined,
            },
        });

        files.value = response?.data?.data?.items ?? [];
        pagination.value = response?.data?.data?.pagination ?? pagination.value;
    } catch (error) {
        files.value = [];
        pagination.value = {
            page: 1,
            per_page: 10,
            total: 0,
            last_page: 1,
        };

        const errorMessage = error?.response?.data?.error ?? '載入檔案列表失敗';
        ElMessage.error(errorMessage);
    }
};

const downloadFile = async (fileId) => {
    try {
        const response = await axios.get(`/api/google/drive/files/${fileId}/download`, {
            responseType: 'blob',
        });

        const disposition = response.headers['content-disposition'] ?? '';
        const fallbackName = `download-${fileId}`;
        const nameMatch = disposition.match(/filename="?([^";]+)"?/i);
        const downloadName = nameMatch?.[1] ?? fallbackName;

        const blobUrl = window.URL.createObjectURL(new Blob([response.data]));
        const link = document.createElement('a');
        link.href = blobUrl;
        link.setAttribute('download', downloadName);
        document.body.appendChild(link);
        link.click();
        link.remove();
        window.URL.revokeObjectURL(blobUrl);
    } catch (error) {
        const errorMessage = error?.response?.data?.error ?? '下載失敗';
        ElMessage.error(errorMessage);
    }
};

const deleteFile = async (item) => {
    try {
        await ElMessageBox.confirm(`確定要刪除 ${item.file_name} 嗎？`, '刪除確認', {
            confirmButtonText: '刪除',
            cancelButtonText: '取消',
            type: 'warning',
        });
    } catch {
        return;
    }

    deletingId.value = item.file_id;

    try {
        await axios.delete(`/api/google/drive/files/${item.file_id}`);
        ElMessage.success('刪除成功');

        const targetPage = files.value.length === 1 && pagination.value.page > 1
            ? pagination.value.page - 1
            : pagination.value.page;

        await fetchFiles(targetPage);
    } catch (error) {
        const errorMessage = error?.response?.data?.error ?? '刪除失敗';
        ElMessage.error(errorMessage);
    } finally {
        deletingId.value = '';
    }
};

const openDriveLink = (url) => {
    window.open(url, '_blank', 'noopener');
};

const loadOAuthStatus = async () => {
    try {
        const response = await axios.get('/api/google/oauth/status');
        oauth.value = response?.data?.data ?? oauth.value;
    } catch {
        oauth.value = {
            connected: false,
            email: null,
            expires_at: null,
        };
    }
};

const connectGoogleDrive = async () => {
    isConnecting.value = true;

    try {
        const response = await axios.get('/api/google/oauth/authorize-url');
        const url = response?.data?.data?.authorize_url;

        if (!url) {
            ElMessage.error('無法取得 Google 授權網址');
            return;
        }

        window.location.href = url;
    } catch (error) {
        const errorMessage = error?.response?.data?.error ?? '啟動 Google OAuth 失敗';
        ElMessage.error(errorMessage);
    } finally {
        isConnecting.value = false;
    }
};

const disconnectGoogleDrive = async () => {
    isDisconnecting.value = true;

    try {
        await axios.delete('/api/google/oauth/disconnect');
        ElMessage.success('已解除 Google Drive 連結');
        await loadOAuthStatus();
        await fetchFiles(1);
    } catch (error) {
        const errorMessage = error?.response?.data?.error ?? '解除連結失敗';
        ElMessage.error(errorMessage);
    } finally {
        isDisconnecting.value = false;
    }
};

const formatFileSize = (size) => {
    const value = Number(size ?? 0);

    if (!Number.isFinite(value) || value <= 0) {
        return '0 B';
    }

    if (value < 1024) {
        return `${value} B`;
    }

    if (value < 1024 * 1024) {
        return `${(value / 1024).toFixed(2)} KB`;
    }

    return `${(value / (1024 * 1024)).toFixed(2)} MB`;
};

onMounted(async () => {
    await loadOAuthStatus();

    const oauthStatus = new URLSearchParams(window.location.search).get('oauth');

    if (oauthStatus === 'connected') {
        ElMessage.success('Google Drive 已連結');
    }

    if (oauthStatus === 'failed') {
        ElMessage.error('Google Drive 連結失敗，請稍後再試');
    }

    await fetchFiles(1);
});
</script>
