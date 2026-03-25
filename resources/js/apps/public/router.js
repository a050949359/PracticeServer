import { h } from 'vue';
import { createRouter, createWebHistory } from 'vue-router';

const RouteViewPlaceholder = {
    render() {
        return h('div', { style: 'display:none' });
    },
};

const routes = [
    {
        path: '/',
        name: 'public.home',
        component: RouteViewPlaceholder,
    },
    {
        path: '/google/vertex/chat',
        name: 'public.vertex.chat',
        component: RouteViewPlaceholder,
    },
    {
        path: '/google/vertex/image',
        name: 'public.vertex.image',
        component: RouteViewPlaceholder,
    },
    {
        path: '/google/vertex/image/detect',
        name: 'public.vertex.detect',
        component: RouteViewPlaceholder,
    },
];

export const publicRouter = createRouter({
    history: createWebHistory(),
    routes,
});
