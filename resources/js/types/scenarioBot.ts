// Типы для сценарных ботов

export interface ScenarioBot {
    id: number;
    name: string;
    description?: string;
    platform: 'whatsapp' | 'telegram' | 'max';
    welcome_message?: string;
    start_step_id?: number;
    is_active: boolean;
    settings?: Record<string, any>;
    created_at: string;
    updated_at: string;
    steps?: ScenarioStep[];
    startStep?: ScenarioStep;
}

export interface ScenarioStep {
    id: number;
    scenario_bot_id: number;
    name: string;
    message: string;
    step_type: 'message' | 'question' | 'menu' | 'final';
    options?: StepOption[];
    next_step_id?: number;
    condition?: string;
    position_x: number;
    position_y: number;
    order: number;
    created_at: string;
    updated_at: string;
}

export interface StepOption {
    text: string;
    next_step_id?: number;
}

export interface ScenarioBotMessage {
    id: number;
    session_id: number;
    role: 'user' | 'assistant' | 'system';
    content: string;
    meta?: Record<string, any>;
    created_at: string;
    updated_at: string;
}

export interface ScenarioBotSession {
    id: number;
    scenario_bot_id: number;
    chat_id: string;
    object_id?: number;
    platform: 'whatsapp' | 'telegram' | 'max';
    current_step_id?: number;
    status: 'running' | 'paused' | 'stopped' | 'completed';
    dialog_data?: Record<string, any>;
    metadata?: Record<string, any>;
    started_at: string;
    stopped_at?: string;
    created_at: string;
    updated_at: string;
    scenarioBot?: ScenarioBot;
    currentStep?: ScenarioStep;
    messages?: ScenarioBotMessage[];
}

// DTOs для создания/обновления

export interface CreateScenarioBotData {
    name: string;
    description?: string;
    platform: 'whatsapp' | 'telegram' | 'max';
    welcome_message?: string;
    is_active?: boolean;
    settings?: Record<string, any>;
}

export interface UpdateScenarioBotData {
    name?: string;
    description?: string;
    platform?: 'whatsapp' | 'telegram' | 'max';
    welcome_message?: string;
    start_step_id?: number;
    is_active?: boolean;
    settings?: Record<string, any>;
}

export interface CreateScenarioStepData {
    name: string;
    message: string;
    step_type: 'message' | 'question' | 'menu' | 'final';
    options?: StepOption[];
    next_step_id?: number;
    condition?: string;
    position_x?: number;
    position_y?: number;
    order?: number;
}

export interface UpdateScenarioStepData {
    name?: string;
    message?: string;
    step_type?: 'message' | 'question' | 'menu' | 'final';
    options?: StepOption[];
    next_step_id?: number;
    condition?: string;
    position_x?: number;
    position_y?: number;
    order?: number;
}

export interface StartScenarioBotSessionData {
    scenario_bot_id: number;
    chat_id: string;
    object_id?: number;
    platform: 'whatsapp' | 'telegram' | 'max';
}

export interface UpdateStepOrderData {
    steps: Array<{
        id: number;
        order: number;
    }>;
}

export interface UpdateStepPositionsData {
    steps: Array<{
        id: number;
        position_x: number;
        position_y: number;
    }>;
}

