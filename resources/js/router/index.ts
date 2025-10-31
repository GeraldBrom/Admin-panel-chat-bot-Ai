import { createRouter, createWebHistory, RouteRecordRaw } from 'vue-router';
import authService from '@/services/authService';

// Lazy loading компонентов
const Login = () => import('@/views/Login.vue');
const Register = () => import('@/views/Register.vue');
const Dashboard = () => import('@/views/Dashboard.vue');

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
        path: '/register',
        name: 'register',
        component: Register,
        meta: { 
            requiresGuest: true,
            title: 'Регистрация',
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
        path: '/:pathMatch(.*)*',
        redirect: '/dashboard',
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

// Navigation guard
router.beforeEach((to, from, next) => {
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

