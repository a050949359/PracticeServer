import { createI18n } from 'vue-i18n';
import en from './locales/en';
import zhTW from './locales/zh-TW';

const supportedLocales = ['zh-TW', 'en'];

function normalizeLocale(rawLocale) {
    if (!rawLocale) {
        return 'zh-TW';
    }

    if (supportedLocales.includes(rawLocale)) {
        return rawLocale;
    }

    if (rawLocale.toLowerCase().startsWith('zh')) {
        return 'zh-TW';
    }

    if (rawLocale.toLowerCase().startsWith('en')) {
        return 'en';
    }

    return 'zh-TW';
}

function resolveLocale() {
    const query = new URLSearchParams(window.location.search);
    const queryLocale = query.get('lang');

    if (queryLocale) {
        const normalizedQueryLocale = normalizeLocale(queryLocale);
        window.localStorage.setItem('app-locale', normalizedQueryLocale);

        return normalizedQueryLocale;
    }

    const savedLocale = window.localStorage.getItem('app-locale');
    if (savedLocale) {
        return normalizeLocale(savedLocale);
    }

    return normalizeLocale(document.documentElement.lang || window.navigator.language);
}

export function createAppI18n() {
    const locale = resolveLocale();

    document.documentElement.lang = locale;

    return createI18n({
        legacy: false,
        locale,
        fallbackLocale: 'zh-TW',
        messages: {
            'zh-TW': zhTW,
            en,
        },
    });
}