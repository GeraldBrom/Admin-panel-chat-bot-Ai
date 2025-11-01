import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router';
import authService from '@/services/authService';

const Login = () => import('@/views/Login.vue');
const Dashboard = () => import('@/views/Dashboard.vue');
const ChatBots = () => import('@/views/ChatBots.vue');
const BotManagement = () => import('@/views/BotManagement.vue');
const Logs = () => import('@/views/Logs.vue');

const routes: RouteRecordRaw[] = [
    {
        path: '/',
        redirect: '/dashboard',
    },
    {
        path: '/login',
        name: 'login',
        component: Login,
        meta: { 
            requiresGuest: true,
            title: 'Вход',
        },
    },
    {
        path: '/dashboard',
        name: 'dashboard',
        component: Dashboard,
        meta: { 
            requiresAuth: true,
            title: 'Панель управления',
        },
    },
    {
        path: '/chat-bots',
        name: 'chat-bots',
        component: ChatBots,
        meta: { 
            requiresAuth: true,
            title: 'Чат боты',
        },
    },
    {
        path: '/bot-management',
        name: 'bot-management',
        component: BotManagement,
        meta: { 
            requiresAuth: true,
            title: 'Управление чат ботами',
        },
    },
    {
        path: '/logs',
        name: 'logs',
        component: Logs,
        meta: { 
            requiresAuth: true,
            title: 'Логи системы',
        },
    },
    {
        path: '/:pathMatch(.*)*',
        redirect: '/dashboard',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach((to, _from, next) => {
    const isAuthenticated = authService.isAuthenticated();
    const requiresAuth = to.meta.requiresAuth;
    const requiresGuest = to.meta.requiresGuest;

    // Установка заголовка страницы
    document.title = to.meta.title ? `${to.meta.title} - Admin Panel` : 'Admin Panel';

    // Защита маршрутов, требующих авторизации
    if (requiresAuth && !isAuthenticated) {
        next({ name: 'login', query: { redirect: to.fullPath } });
        return;
    }

    // Защита маршрутов для гостей (login, register)
    if (requiresGuest && isAuthenticated) {
        next({ name: 'dashboard' });
        return;
    }

    next();
});

export default router;

