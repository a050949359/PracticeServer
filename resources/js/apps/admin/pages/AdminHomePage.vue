<template>
    <el-card class="spa-panel" shadow="hover">
        <template #header>
            <div class="spa-panel-header">
                <el-tag effect="dark" type="info">Admin Area</el-tag>
                <span class="spa-panel-title">Element Plus 已接上</span>
            </div>
        </template>

        <h1 class="spa-title">這是管理後台 SPA</h1>
        <p class="spa-description">目前既有功能先歸在 admin 區，登入與註冊以中間彈窗方式操作。</p>

        <el-space class="spa-actions-row" wrap>
            <el-button v-if="!isAuthenticated" plain @click="openLoginDialog">立即登入</el-button>
            <el-button v-if="!isAuthenticated" type="primary" @click="openRegisterDialog">立即註冊</el-button>
            <el-button v-if="isAuthenticated" plain @click="openProfileDialog">個人資料</el-button>
            <el-button v-if="isAuthenticated" type="primary" @click="openInviteDialog">發送邀請</el-button>
            <el-button plain @click="goDrivePage">Google Drive</el-button>
            <el-button plain @click="goCsvExportPage">CSV 匯出</el-button>
            <el-button plain>查看更多</el-button>
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
    goDrivePage: {
        type: Function,
        required: true,
    },
    goCsvExportPage: {
        type: Function,
        required: true,
    },
});
</script>
