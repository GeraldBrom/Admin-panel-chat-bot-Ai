import api from './api';
import type { 
    LoginCredentials, 
    RegisterData, 
    AuthResponse, 
    User,
    ApiResponse 
} from '@/types';

class AuthService {
    /**
     * Регистрация нового пользователя
     */
    async register(data: RegisterData): Promise<AuthResponse> {
        const response = await api.post<ApiResponse<AuthResponse>>('/auth/register', data);
        return {
            user: response.data.data.user,
            token: response.data.data.token,
        };
    }

    /**
     * Авторизация пользователя
     */
    async login(credentials: LoginCredentials): Promise<AuthResponse> {
        const response = await api.post<ApiResponse<AuthResponse>>('/auth/login', credentials);
        return {
            user: response.data.data.user,
            token: response.data.data.token,
        };
    }

    /**
     * Выход из системы
     */
    async logout(): Promise<void> {
        await api.post('/auth/logout');
    }

    /**
     * Получить текущего пользователя
     */
    async me(): Promise<User> {
        const response = await api.get<ApiResponse<{ user: User }>>('/auth/me');
        return response.data.data.user;
    }

    /**
     * Выход из всех устройств
     */
    async logoutFromAllDevices(): Promise<void> {
        await api.post('/auth/logout-all');
    }

    /**
     * Сохранить токен
     */
    saveToken(token: string): void {
        localStorage.setItem('auth_token', token);
    }

    /**
     * Получить токен
     */
    getToken(): string | null {
        return localStorage.getItem('auth_token');
    }

    /**
     * Удалить токен
     */
    removeToken(): void {
        localStorage.removeItem('auth_token');
    }

    /**
     * Сохранить пользователя
     */
    saveUser(user: User): void {
        localStorage.setItem('user', JSON.stringify(user));
    }

    /**
     * Получить пользователя
     */
    getUser(): User | null {
        const user = localStorage.getItem('user');
        return user ? JSON.parse(user) : null;
    }

    /**
     * Удалить пользователя
     */
    removeUser(): void {
        localStorage.removeItem('user');
    }

    /**
     * Проверить аутентификацию
     */
    isAuthenticated(): boolean {
        return !!this.getToken();
    }
}

export default new AuthService();

