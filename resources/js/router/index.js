import { createRouter, createWebHistory } from 'vue-router';
import LoginPage from '../pages/LoginPage.vue';
import SettingsPage from '../pages/SettingsPage.vue';
import api from '../services/api';

const router = createRouter({
    history: createWebHistory(),
    routes: [
        {
            path: '/',
            redirect: '/settings',
        },
        {
            path: '/login',
            component: LoginPage,
        },
        {
            path: '/settings',
            component: SettingsPage,
            meta: {
                requiresAuth: true,
            },
        },
    ],
});

router.beforeEach(async (to) => {
    if (!to.meta.requiresAuth) {
        return true;
    }

    try {
        await api.get('/api/user');

        return true;
    } catch (error) {
        if (error.response?.status === 401) {
            return {
                path: '/login',
                query: {
                    redirect: to.fullPath,
                },
            };
        }

        return false;
    }
});

export default router;
