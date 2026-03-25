<template>
    <el-card class="spa-panel spa-chat-panel" shadow="hover">
        <template #header>
            <div class="spa-panel-header spa-chat-header">
                <div>
                    <el-tag type="warning">Vertex AI</el-tag>
                    <span class="spa-panel-title">簡易對話頁</span>
                </div>
                <el-button plain @click="goHome">回首頁</el-button>
            </div>
        </template>

        <div class="spa-chat-history">
            <article
                v-for="(message, index) in chatMessages"
                :key="`${message.role}-${index}`"
                class="spa-chat-message"
                :class="message.role === 'user' ? 'spa-chat-message-user' : 'spa-chat-message-model'"
            >
                <div class="spa-chat-role">{{ message.role === 'user' ? '你' : 'Vertex AI' }}</div>
                <p class="spa-chat-text">{{ message.content }}</p>
            </article>
        </div>

        <div class="spa-chat-form">
            <el-input
                v-model="prompt"
                type="textarea"
                :rows="4"
                resize="none"
                placeholder="輸入問題，送出後會呼叫 Vertex AI"
                @keydown.ctrl.enter="submitChat"
            />
            <div class="spa-chat-actions">
                <span class="spa-chat-hint">Ctrl + Enter 可送出</span>
                <el-button type="primary" :loading="isSending" @click="submitChat">送出提問</el-button>
            </div>
        </div>
    </el-card>
</template>

<script setup>
import axios from 'axios';
import { ElMessage } from 'element-plus';
import { useRouter } from 'vue-router';

const prompt = ref('');
const isSending = ref(false);
const router = useRouter();
const chatMessages = ref([
    {
        role: 'model',
        content: '你好，這裡是 Vertex AI 測試頁。請直接輸入問題。',
    },
]);

const goHome = () => {
    router.push('/');
};

const submitChat = async () => {
    const normalizedPrompt = prompt.value.trim();

    if (normalizedPrompt === '' || isSending.value) {
        return;
    }

    const history = chatMessages.value
        .filter((message) => message.role === 'user' || message.role === 'model')
        .map((message) => ({
            role: message.role,
            content: message.content,
        }));

    chatMessages.value.push({
        role: 'user',
        content: normalizedPrompt,
    });

    prompt.value = '';
    isSending.value = true;

    try {
        const response = await axios.post('/api/google/vertex/chat', {
            prompt: normalizedPrompt,
            messages: history,
        });

        chatMessages.value.push({
            role: 'model',
            content: response.data?.data?.reply ?? 'Vertex AI 沒有回傳內容。',
        });
    } catch (error) {
        const errorMessage = error?.response?.data?.error ?? 'Vertex AI 呼叫失敗';

        chatMessages.value.push({
            role: 'model',
            content: `錯誤：${errorMessage}`,
        });

        ElMessage.error(errorMessage);
    } finally {
        isSending.value = false;
    }
};
</script>
