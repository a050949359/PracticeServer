<template>
    <el-card class="spa-panel" shadow="hover">
        <template #header>
            <div class="spa-panel-header">
                <el-tag type="success">Public Area</el-tag>
                <span class="spa-panel-title">前台入口</span>
            </div>
        </template>

        <h1 class="spa-title">這是一般使用者頁面</h1>
        <p class="spa-description">前台與後台已分開登入。這裡提供一般使用者自己的登入與註冊流程。</p>

        <el-space class="spa-actions-row" wrap>
            <el-button v-if="!isAuthenticated" plain @click="openLoginDialog">立即登入</el-button>
            <el-button v-if="!isAuthenticated" type="primary" @click="openRegisterDialog">立即註冊</el-button>
            <el-button v-if="isAuthenticated" plain @click="openProfileDialog">個人資料</el-button>
            <el-button v-if="isAuthenticated" type="primary" @click="openInviteDialog">發送邀請</el-button>
        </el-space>
    </el-card>
</template>

<script setup>
defineProps({
    isAuthenticated: {
        type: Boolean,
        default: false,
    },
    openLoginDialog: {
        type: Function,
        required: true,
    },
    openRegisterDialog: {
        type: Function,
        required: true,
    },
    openProfileDialog: {
        type: Function,
        required: true,
    },
    openInviteDialog: {
        type: Function,
        required: true,
    },
});
</script>
