<template>
    <header class="spa-topbar">
        <div class="spa-topbar-inner">
            <a :href="brandHref" class="spa-brand">{{ brandLabel }}</a>
            <nav class="spa-actions" :aria-label="navLabel">
                <template v-if="authenticated">
                    <el-dropdown trigger="click" @command="handleMenuAction">
                        <button class="spa-btn spa-btn-ghost spa-user-trigger" type="button">
                            <span v-if="userStatusLabel" class="spa-user-status">{{ userStatusLabel }}</span>
                            <span class="spa-user-name">{{ userLabel }}</span>
                            <span aria-hidden="true">▾</span>
                        </button>

                        <template #dropdown>
                            <el-dropdown-menu>
                                <el-dropdown-item v-for="item in menuItems" :key="item.key" :command="item.key">
                                    {{ item.label }}
                                </el-dropdown-item>
                            </el-dropdown-menu>
                        </template>
                    </el-dropdown>
                </template>

                <template v-else>
                    <template v-for="action in actions" :key="action.key">
                        <a
                            v-if="action.href"
                            :href="action.href"
                            class="spa-btn"
                            :class="action.variant === 'primary' ? 'spa-btn-primary' : 'spa-btn-ghost'"
                        >
                            {{ action.label }}
                        </a>
                        <el-button
                            v-else
                            :plain="action.variant !== 'primary'"
                            :type="action.variant === 'primary' ? 'primary' : undefined"
                            :class="action.variant === 'primary' ? 'spa-btn-primary' : 'spa-btn-ghost'"
                            @click="$emit('action', action.key)"
                        >
                            {{ action.label }}
                        </el-button>
                    </template>
                </template>
            </nav>
        </div>
    </header>
</template>

<script setup>
defineProps({
    brandHref: {
        type: String,
        required: true,
    },
    brandLabel: {
        type: String,
        required: true,
    },
    navLabel: {
        type: String,
        default: 'primary navigation',
    },
    actions: {
        type: Array,
        default: () => [],
    },
    authenticated: {
        type: Boolean,
        default: false,
    },
    userLabel: {
        type: String,
        default: '會員',
    },
    userStatusLabel: {
        type: String,
        default: '',
    },
    menuItems: {
        type: Array,
        default: () => [],
    },
});

const emit = defineEmits(['action']);

const handleMenuAction = (actionKey) => {
    emit('action', actionKey);
};
</script>
