import { useAuthStore } from '@/stores/authStore';
import { useRouter } from 'vue-router';
import type { LoginCredentials, RegisterData } from '@/types';

export function useAuth() {
    const authStore = useAuthStore();
    const router = useRouter();

    const login = async (credentials: LoginCredentials) => {
        try {
            await authStore.login(credentials);
            router.push({ name: 'dashboard' });
        } catch (error) {
            console.error('Login failed:', error);
            throw error;
        }
    };

    const register = async (data: RegisterData) => {
        try {
            await authStore.register(data);
            router.push({ name: 'dashboard' });
        } catch (error) {
            console.error('Registration failed:', error);
            throw error;
        }
    };

    const logout = async () => {
        try {
            await authStore.logout();
            router.push({ name: 'login' });
        } catch (error) {
            console.error('Logout failed:', error);
        }
    };

    return {
        user: authStore.user,
        isAuthenticated: authStore.isAuthenticated,
        loading: authStore.loading,
        error: authStore.error,
        userName: authStore.userName,
        userEmail: authStore.userEmail,
        login,
        register,
        logout,
        clearError: authStore.clearError,
    };
}

