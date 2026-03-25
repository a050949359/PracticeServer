<template>
    <el-card class="spa-panel spa-chat-panel" shadow="hover">
        <template #header>
            <div class="spa-panel-header spa-chat-header">
                <div>
                    <el-tag type="warning">Vertex AI</el-tag>
                    <span class="spa-panel-title">影像分析頁</span>
                </div>
                <el-button plain @click="goHome">回首頁</el-button>
            </div>
        </template>

        <div class="spa-chat-form">
            <el-input
                v-model="prompt"
                type="textarea"
                :rows="4"
                resize="none"
                placeholder="輸入要分析影像的需求，例如：描述圖片重點"
            />

            <label class="spa-image-upload-label" for="vertex-image-file-input">選擇圖片</label>
            <input
                id="vertex-image-file-input"
                class="spa-image-upload-input"
                type="file"
                accept="image/*"
                @change="handleImageChange"
            />

            <p v-if="selectedImageName" class="spa-chat-hint">已選擇：{{ selectedImageName }}</p>

            <img v-if="imagePreviewUrl" :src="imagePreviewUrl" alt="image preview" class="spa-image-preview" />

            <div class="spa-chat-actions">
                <span class="spa-chat-hint">支援常見圖片格式，建議 10MB 以下</span>
                <el-button type="primary" :loading="isSending" @click="submitImage">送出影像分析</el-button>
            </div>
        </div>

        <div v-if="reply" class="spa-chat-history" style="margin-top: 1rem">
            <article class="spa-chat-message spa-chat-message-model">
                <div class="spa-chat-role">Vertex AI</div>
                <p class="spa-chat-text">{{ reply }}</p>
            </article>
        </div>
    </el-card>
</template>

<script setup>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { useRouter } from 'vue-router';

const router = useRouter();
const prompt = ref('');
const selectedImage = ref(null);
const selectedImageName = ref('');
const imagePreviewUrl = ref('');
const reply = ref('');
const isSending = ref(false);

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

const submitImage = async () => {
    const normalizedPrompt = prompt.value.trim();

    if (normalizedPrompt === '' || !selectedImage.value || isSending.value) {
        ElMessage.error('請先輸入提示文字並選擇圖片');
        return;
    }

    const payload = new FormData();
    payload.append('prompt', normalizedPrompt);
    payload.append('image', selectedImage.value);

    isSending.value = true;

    try {
        const response = await axios.post('/api/google/vertex/image', payload, {
            headers: {
                'Content-Type': 'multipart/form-data',
            },
        });

        reply.value = response.data?.data?.reply ?? 'Vertex AI 沒有回傳內容。';
    } catch (error) {
        const errorMessage = error?.response?.data?.error ?? 'Vertex AI 影像呼叫失敗';
        ElMessage.error(errorMessage);
    } finally {
        isSending.value = false;
    }
};
</script>
