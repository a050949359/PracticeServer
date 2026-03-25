import './bootstrap';
import '../css/app.css';
import '../css/spa.css';
import 'element-plus/es/components/message/style/css';
import { createApp } from 'vue';
import { createAppI18n } from './i18n';

export const SPA_APP_SELECTOR = '#app';

export const hasSpaMountTarget = () => {
    return document.querySelector(SPA_APP_SELECTOR) !== null;
};

export const createSpaApp = (rootComponent) => {
    const app = createApp(rootComponent);

    app.use(createAppI18n());

    return app;
};
