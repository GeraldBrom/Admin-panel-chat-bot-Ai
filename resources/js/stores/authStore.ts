import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import authService from '@/services/authService';
import type { User, LoginCredentials } from '@/types';

export const useAuthStore = defineStore('auth', () => {
    // State
    const user = ref<User | null>(authService.getUser());
    const token = ref<string | null>(authService.getToken());
    const loading = ref(false);
    const error = ref<string | null>(null);

    // Getters
    const isAuthenticated = computed(() => !!token.value && !!user.value);
    const userName = computed(() => user.value?.name || '');
    const userEmail = computed(() => user.value?.email || '');

    // Actions
    async function login(credentials: LoginCredentials) {
        try {
            loading.value = true;
            error.value = null;

            const response = await authService.login(credentials);
            
            user.value = response.user;
            token.value = response.token;
            
            authService.saveToken(response.token);
            authService.saveUser(response.user);
            
            return true;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка авторизации';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function logout() {
        try {
            loading.value = true;
            
            await authService.logout();
            
            user.value = null;
            token.value = null;
            
            authService.removeToken();
            authService.removeUser();
        } catch (err: any) {
            console.error('Logout error:', err);
            // Даже при ошибке очищаем локальные данные
            user.value = null;
            token.value = null;
            authService.removeToken();
            authService.removeUser();
        } finally {
            loading.value = false;
        }
    }

    async function fetchUser() {
        try {
            loading.value = true;
            error.value = null;

            const fetchedUser = await authService.me();
            user.value = fetchedUser;
            authService.saveUser(fetchedUser);
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка загрузки пользователя';
            // При ошибке очищаем данные
            user.value = null;
            token.value = null;
            authService.removeToken();
            authService.removeUser();
            throw err;
        } finally {
            loading.value = false;
        }
    }

    function clearError() {
        error.value = null;
    }

    return {
        // State
        user,
        token,
        loading,
        error,
        
        // Getters
        isAuthenticated,
        userName,
        userEmail,
        
        // Actions
        login,
        logout,
        fetchUser,
        clearError,
    };
});

