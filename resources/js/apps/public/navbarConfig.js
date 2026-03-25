export const GUEST_NAVBAR_ACTION_KEYS = Object.freeze({
    login: 'login',
    register: 'register',
});

export const AUTH_MENU_ITEM_KEYS = Object.freeze({
    profile: 'profile',
    invite: 'invite',
    logout: 'logout',
});

export const buildGuestNavbarActions = (t) => [
    {
        key: GUEST_NAVBAR_ACTION_KEYS.login,
        label: t('navbar.actions.login'),
        variant: 'ghost',
    },
    {
        key: GUEST_NAVBAR_ACTION_KEYS.register,
        label: t('navbar.actions.register'),
        variant: 'primary',
    },
];

export const buildAuthMenuItems = (t) => [
    {
        key: AUTH_MENU_ITEM_KEYS.profile,
        label: t('navbar.actions.profile'),
    },
    {
        key: AUTH_MENU_ITEM_KEYS.invite,
        label: t('navbar.actions.invite'),
    },
    {
        key: AUTH_MENU_ITEM_KEYS.logout,
        label: t('navbar.actions.logout'),
    },
];

export const NAVBAR_DROPDOWN_MENU_KEYS = Object.freeze({
    vertexAi: 'vertex-ai',
    vertexChat: 'vertex-chat',
    vertexImage: 'vertex-image',
    vertexDetect: 'vertex-detect',
});

export const buildNavbarDropdownMenus = (t) => [
    {
        key: NAVBAR_DROPDOWN_MENU_KEYS.vertexAi,
        label: t('navbar.vertex.label'),
        variant: 'ghost',
        items: [
            {
                key: NAVBAR_DROPDOWN_MENU_KEYS.vertexChat,
                label: t('navbar.vertex.chat'),
                to: '/google/vertex/chat',
            },
            {
                key: NAVBAR_DROPDOWN_MENU_KEYS.vertexImage,
                label: t('navbar.vertex.image'),
                to: '/google/vertex/image',
            },
            {
                key: NAVBAR_DROPDOWN_MENU_KEYS.vertexDetect,
                label: t('navbar.vertex.detect'),
                to: '/google/vertex/image/detect',
            },
        ],
    },
];

