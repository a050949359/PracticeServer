<template>
    <el-card class="spa-panel spa-chat-panel" shadow="hover">
        <template #header>
            <div class="spa-panel-header spa-chat-header">
                <div>
                    <el-tag type="warning">Vertex AI</el-tag>
                    <span class="spa-panel-title">OCR 文字辨識頁</span>
                </div>
                <el-button plain @click="goHome">回首頁</el-button>
            </div>
        </template>

        <div class="spa-chat-form">
            <el-select
                v-model="selectedTypes"
                multiple
                collapse-tags
                collapse-tags-tooltip
                :max-collapse-tags="2"
                placeholder="選擇辨識 type（可多選）"
                class="spa-detect-type-select"
            >
                <el-option
                    v-for="option in typeOptions"
                    :key="option.value"
                    :label="option.label"
                    :value="option.value"
                />
            </el-select>

            <label class="spa-image-upload-label" for="vertex-detect-file-input">選擇圖片（OCR）</label>
            <input
                id="vertex-detect-file-input"
                class="spa-image-upload-input"
                type="file"
                accept="image/*"
                @change="handleImageChange"
            />

            <p v-if="selectedImageName" class="spa-chat-hint">已選擇：{{ selectedImageName }}</p>

            <img v-if="imagePreviewUrl" :src="imagePreviewUrl" alt="detect preview" class="spa-image-preview" />

            <div class="spa-chat-actions">
                <span class="spa-chat-hint">Cloud Vision API 會回傳辨識文字</span>
                <el-button type="primary" :loading="isSending" @click="submitDetection">開始辨識</el-button>
            </div>
        </div>

        <div v-if="ocrText" class="spa-chat-history" style="margin-top: 1rem">
            <article class="spa-chat-message spa-chat-message-model">
                <div class="spa-chat-role">OCR 結果</div>
                <p class="spa-chat-text">{{ ocrText }}</p>
            </article>
        </div>

        <div v-if="detectedObjects.length > 0" class="spa-chat-history" style="margin-top: 1rem">
            <article
                v-for="(item, index) in detectedObjects"
                :key="`${item.name}-${index}`"
                class="spa-chat-message spa-chat-message-model"
            >
                <div class="spa-chat-role">物件：{{ item.name }}（{{ (item.score * 100).toFixed(1) }}%）</div>
                <p class="spa-chat-text">座標：{{ formatCoordinates(item.bounding_poly) }}</p>
            </article>
        </div>

        <div v-if="savedRecord" class="spa-chat-history" style="margin-top: 1rem">
            <article class="spa-chat-message spa-chat-message-model">
                <div class="spa-chat-role">已儲存辨識</div>
                <p class="spa-chat-text">圖片名稱：{{ savedRecord.image_name }}</p>
                <a :href="savedRecord.image_url" target="_blank" rel="noopener">檢視儲存圖片</a>
            </article>
        </div>

        <div v-if="historyItems.length > 0" class="spa-chat-history" style="margin-top: 1rem">
            <article
                v-for="item in historyItems"
                :key="item.id"
                class="spa-chat-message spa-chat-message-model"
            >
                <div class="spa-chat-role">{{ item.image_name }}</div>
                <p class="spa-chat-text">{{ item.text }}</p>
            </article>
        </div>
    </el-card>
</template>

<script setup>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { onMounted } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const selectedImage = ref(null);
const selectedImageName = ref('');
const imagePreviewUrl = ref('');
const ocrText = ref('');
const isSending = ref(false);
const selectedTypes = ref(['DOCUMENT_TEXT_DETECTION']);
const savedRecord = ref(null);
const historyItems = ref([]);
const detectedObjects = ref([]);

const typeOptions = [
    { label: '文件 OCR', value: 'DOCUMENT_TEXT_DETECTION' },
    { label: '一般文字 OCR', value: 'TEXT_DETECTION' },
    { label: '標籤辨識', value: 'LABEL_DETECTION' },
    { label: '物件定位', value: 'OBJECT_LOCALIZATION' },
    { label: '人臉偵測', value: 'FACE_DETECTION' },
    { label: 'Logo 偵測', value: 'LOGO_DETECTION' },
    { label: '地標偵測', value: 'LANDMARK_DETECTION' },
    { label: '安全內容偵測', value: 'SAFE_SEARCH_DETECTION' },
];

const goHome = () => {
    router.push('/');
};

const handleImageChange = (event) => {
    const file = event.target?.files?.[0] ?? null;

    selectedImage.value = file;
    selectedImageName.value = file?.name ?? '';

    if (!file) {
        imagePreviewUrl.value = '';
        return;
    }

    imagePreviewUrl.value = URL.createObjectURL(file);
};

const submitDetection = async () => {
    if (!selectedImage.value || isSending.value) {
        ElMessage.error('請先選擇圖片');
        return;
    }

    const payload = new FormData();
    payload.append('image', selectedImage.value);
    selectedTypes.value.forEach((type) => {
        payload.append('types[]', type);
    });

    isSending.value = true;
    ocrText.value = '';
    savedRecord.value = null;
    detectedObjects.value = [];

    try {
        const response = await axios.post('/api/google/vertex/image/detect', payload, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        ocrText.value = response.data?.data?.text ?? '';
        detectedObjects.value = response.data?.data?.objects ?? [];
        savedRecord.value = response.data?.data?.record ?? null;
        await fetchHistory();

        if (ocrText.value === '' && detectedObjects.value.length === 0) {
            ElMessage.info('未辨識到結果');
        }
    } catch (error) {
        const errorMessage = error?.response?.data?.error ?? 'Cloud Vision OCR 呼叫失敗';
        ElMessage.error(errorMessage);
    } finally {
        isSending.value = false;
    }
};

const fetchHistory = async () => {
    try {
        const response = await axios.get('/api/google/vertex/image/detect/history');
        historyItems.value = response.data?.data?.items ?? [];
    } catch {
        historyItems.value = [];
    }
};

onMounted(() => {
    void fetchHistory();
});

const formatCoordinates = (vertices) => {
    if (!Array.isArray(vertices) || vertices.length === 0) {
        return '無座標';
    }

    return vertices
        .map((vertex) => `(${Number(vertex.x).toFixed(3)}, ${Number(vertex.y).toFixed(3)})`)
        .join(' -> ');
};
</script>
