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
    chat_id: string;
    object_id: number;
    platform: 'whatsapp';
    bot_config_id?: number;
    status: 'running' | 'paused' | 'stopped' | 'completed';
    dialog_state?: Record<string, any>;
    metadata?: Record<string, any>;
    started_at: string;
    stopped_at?: string;
    created_at: string;
    updated_at: string;
    dialog_id?: string;
    messages?: Message[];
}

export interface PlatformConfig {
    platform: 'whatsapp';
    api_key?: string;
    bot_token?: string;
    webhook_url?: string;
    settings?: Record<string, any>;
}

export interface BotSession {
    id: number;
    chat_id: string;
    object_id: number;
    platform: 'whatsapp';
    bot_config_id?: number;
    status: 'running' | 'paused' | 'stopped' | 'completed';
    dialog_state?: Record<string, any>;
    metadata?: Record<string, any>;
    started_at: string;
    stopped_at?: string;
    created_at: string;
    updated_at: string;
}

export interface Message {
    id: number;
    dialog_id: string;
    role: 'user' | 'assistant' | 'system';
    content: string;
    tokens_in?: number;
    tokens_out?: number;
    meta?: Record<string, any>;
    created_at: string;
}

export interface VectorStore {
    name: string;
    id: string;
}

export interface BotConfig {
    id: number;
    name: string;
    platform: 'whatsapp';
    prompt: string;
    scenario_description: string;
    temperature: number;
    max_tokens: number;
    vector_store_id_main?: string;
    vector_store_id_objections?: string;
    kickoff_message?: string;
    vector_stores?: VectorStore[];
    openai_model?: string;
    openai_service_tier?: string;
    settings?: Record<string, any>;
    created_at: string;
    updated_at: string;
}

// Create/Update DTOs
export interface CreateChatBotData {
    name: string;
    platform: 'whatsapp';
    client_phone?: string;
    object_id: string;
    platform_config?: Partial<PlatformConfig>;
}

export interface CreateSessionData {
    chat_id: string;
    object_id: number;
    bot_config_id?: number;
}

export interface CreateMessageData {
    dialog_id: string;
    content: string;
    role: 'user' | 'assistant' | 'system';
}

export interface CreateBotConfigData {
    name: string;
    platform: 'whatsapp';
    prompt: string;
    scenario_description: string;
    temperature?: number;
    max_tokens?: number;
    vector_store_id_main?: string;
    vector_store_id_objections?: string;
    kickoff_message?: string;
    vector_stores?: VectorStore[];
    openai_model?: string;
    openai_service_tier?: string;
    settings?: Record<string, any>;
}

