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

export const ADMIN_DROPDOWN_MENU_KEYS = Object.freeze({
    google: 'google',
    googleDrive: 'google-drive',
    queue: 'queue',
    csvExport: 'csv-export',
});

export const buildAdminDropdownMenus = (t) => [
    {
        key: ADMIN_DROPDOWN_MENU_KEYS.google,
        label: t('navbar.google.label'),
        variant: 'ghost',
        items: [
            {
                key: ADMIN_DROPDOWN_MENU_KEYS.googleDrive,
                label: t('navbar.google.drive'),
                to: '/admin/google/drive',
            },
        ],
    },
    {
        key: ADMIN_DROPDOWN_MENU_KEYS.queue,
        label: t('navbar.queue.label'),
        variant: 'ghost',
        items: [
            {
                key: ADMIN_DROPDOWN_MENU_KEYS.csvExport,
                label: t('navbar.queue.csvExport'),
                to: '/admin/exports/csv',
            },
        ],
    },
];
