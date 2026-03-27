import PublicHomePage from './pages/PublicHomePage.vue';
import PublicVertexChatPage from './pages/PublicVertexChatPage.vue';
import PublicVertexDetectPage from './pages/PublicVertexDetectPage.vue';
import PublicVertexImagePage from './pages/PublicVertexImagePage.vue';
import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/',
        name: 'public.home',
        component: PublicHomePage,
        meta: {
            titleKey: 'pages.public.home.title',
            breadcrumbKeys: ['pages.public.home.breadcrumb'],
        },
    },
    {
        path: '/google/vertex/chat',
        name: 'public.vertex.chat',
        component: PublicVertexChatPage,
        meta: {
            titleKey: 'pages.public.vertex.chat.title',
            breadcrumbKeys: ['pages.public.home.breadcrumb', 'pages.public.vertex.group', 'pages.public.vertex.chat.breadcrumb'],
        },
    },
    {
        path: '/google/vertex/image',
        name: 'public.vertex.image',
        component: PublicVertexImagePage,
        meta: {
            titleKey: 'pages.public.vertex.image.title',
            breadcrumbKeys: ['pages.public.home.breadcrumb', 'pages.public.vertex.group', 'pages.public.vertex.image.breadcrumb'],
        },
    },
    {
        path: '/google/vertex/image/detect',
        name: 'public.vertex.detect',
        component: PublicVertexDetectPage,
        meta: {
            titleKey: 'pages.public.vertex.detect.title',
            breadcrumbKeys: ['pages.public.home.breadcrumb', 'pages.public.vertex.group', 'pages.public.vertex.detect.breadcrumb'],
        },
    },
];

export const publicRouter = createRouter({
    history: createWebHistory(),
    routes,
});
