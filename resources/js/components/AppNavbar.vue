<template>
    <header class="spa-topbar">
        <div class="spa-topbar-inner">
            <div class="spa-topbar-start">
                <a :href="brandHref" class="spa-brand" @click="handleNavigate($event, brandHref)">{{ brandLabel }}</a>

                <nav v-if="links.length > 0 || dropdownMenus.length > 0" class="spa-nav-left" :aria-label="leftNavLabel">
                    <template v-for="menu in dropdownMenus" :key="menu.key">
                        <el-dropdown trigger="hover" popper-class="spa-nav-dropdown-popper">
                            <button class="spa-nav-menu-trigger" type="button">
                                <span>{{ menu.label }}</span>
                            </button>

                            <template #dropdown>
                                <el-dropdown-menu>
                                    <el-dropdown-item v-for="item in menu.items" :key="item.key">
                                        <a
                                            :href="item.to ?? item.href"
                                            class="spa-dropdown-link"
                                            @click="handleNavigate($event, item.to ?? item.href)"
                                        >
                                            {{ item.label }}
                                        </a>
                                    </el-dropdown-item>
                                </el-dropdown-menu>
                            </template>
                        </el-dropdown>
                    </template>

                    <template v-for="link in links" :key="link.key">
                        <a
                            :href="link.to ?? link.href"
                            class="spa-btn"
                            :class="link.variant === 'primary' ? 'spa-btn-primary' : 'spa-btn-ghost'"
                            @click="handleNavigate($event, link.to ?? link.href)"
                        >
                            {{ link.label }}
                        </a>
                    </template>
                </nav>
            </div>

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
                            @click="handleNavigate($event, action.href)"
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
const props = defineProps({
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
    leftNavLabel: {
        type: String,
        default: 'secondary navigation',
    },
    dropdownMenus: {
        type: Array,
        default: () => [],
    },
    links: {
        type: Array,
        default: () => [],
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
    navigate: {
        type: Function,
        default: null,
    },
});

const emit = defineEmits(['action']);

const handleMenuAction = (actionKey) => {
    emit('action', actionKey);
};

const handleNavigate = (event, target) => {
    if (!props.navigate || !target) {
        return;
    }

    event.preventDefault();
    props.navigate(target);
};
</script>
