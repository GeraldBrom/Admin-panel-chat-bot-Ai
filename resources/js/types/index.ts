// User Types
export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    created_at: string;
    updated_at: string;
}

// Auth Types
export interface LoginCredentials {
    email: string;
    password: string;
    remember?: boolean;
}

export interface RegisterData {
    name: string;
    email: string;
    password: string;
    password_confirmation: string;
}

export interface AuthResponse {
    user: User;
    token: string;
}

// API Response Types
export interface ApiResponse<T = any> {
    data: T;
    message?: string;
}

export interface ApiError {
    message: string;
    errors?: Record<string, string[]>;
}

// Bot Types (для будущего расширения)
export interface Bot {
    id: number;
    name: string;
    status: 'active' | 'inactive';
    message_count: number;
    created_at: string;
    updated_at: string;
}

