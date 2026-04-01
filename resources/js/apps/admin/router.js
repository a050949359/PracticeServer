import AdminGoogleDrivePage from './pages/AdminGoogleDrivePage.vue';
import AdminCsvChannelPage from './pages/AdminCsvChannelPage.vue';
import AdminCsvExportPage from './pages/AdminCsvExportPage.vue';
import AdminHomePage from './pages/AdminHomePage.vue';
import { createRouter, createWebHistory } from 'vue-router';

const routes = [
    {
        path: '/admin',
        name: 'admin.home',
        component: AdminHomePage,
        meta: {
            requiresAuth: false,
            titleKey: 'pages.admin.home.title',
            breadcrumbKeys: ['pages.admin.home.breadcrumb'],
        },
    },
    {
        path: '/admin/google/drive',
        name: 'admin.google.drive',
        component: AdminGoogleDrivePage,
        meta: {
            requiresAuth: true,
            titleKey: 'pages.admin.drive.title',
            breadcrumbKeys: ['pages.admin.home.breadcrumb', 'pages.admin.drive.breadcrumb'],
        },
    },
    {
        path: '/admin/exports/csv',
        name: 'admin.exports.csv',
        component: AdminCsvExportPage,
        meta: {
            requiresAuth: true,
            titleKey: 'pages.admin.csvExport.title',
            breadcrumbKeys: ['pages.admin.home.breadcrumb', 'pages.admin.csvExport.breadcrumb'],
        },
    },
    {
        path: '/admin/exports/csv/channels',
        name: 'admin.exports.csvChannels',
        component: AdminCsvChannelPage,
        meta: {
            requiresAuth: true,
            titleKey: 'pages.admin.csvChannels.title',
            breadcrumbKeys: ['pages.admin.home.breadcrumb', 'pages.admin.csvExport.breadcrumb', 'pages.admin.csvChannels.breadcrumb'],
        },
    },
];

export const adminRouter = createRouter({
    history: createWebHistory(),
    routes,
});
