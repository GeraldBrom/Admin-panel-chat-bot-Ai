// Bot Types
export interface Bot {
    id: number;
    name: string;
    status: 'active' | 'inactive';
    message_count: number;
    created_at: string;
    updated_at: string;
}

export interface ChatBot {
    id: number;
    name: string;
    platform: 'whatsapp' | 'telegram' | 'max';
    client_phone?: string;
    object_id: string;
    status: 'online' | 'offline' | 'processing';
    active_sessions_count: number;
    total_messages: number;
    created_at: string;
    updated_at: string;
}

export interface PlatformConfig {
    platform: 'whatsapp' | 'telegram' | 'max';
    api_key?: string;
    bot_token?: string;
    webhook_url?: string;
    settings?: Record<string, any>;
}

export interface BotSession {
    id: number;
    chat_bot_id: number;
    session_key: string;
    status: 'active' | 'inactive' | 'paused';
    last_message_at: string;
    message_count: number;
    created_at: string;
    updated_at: string;
}

export interface Message {
    id: number;
    session_id: number;
    sender: 'user' | 'bot';
    content: string;
    metadata?: Record<string, any>;
    created_at: string;
}

export interface BotConfig {
    id: number;
    chat_bot_id: number;
    name: string;
    prompt: string;
    scenario: string;
    temperature?: number;
    max_tokens?: number;
    is_active: boolean;
    created_at: string;
    updated_at: string;
}

// Create/Update DTOs
export interface CreateChatBotData {
    name: string;
    platform: 'whatsapp' | 'telegram' | 'max';
    client_phone?: string;
    object_id: string;
    platform_config?: Partial<PlatformConfig>;
}

export interface CreateSessionData {
    chat_bot_id: number;
}

export interface CreateMessageData {
    session_id: number;
    content: string;
    sender: 'user' | 'bot';
}

export interface CreateBotConfigData {
    chat_bot_id: number;
    name: string;
    prompt: string;
    scenario: string;
    temperature?: number;
    max_tokens?: number;
}

