import api from './api';
import type {
    ChatBot,
    BotSession,
    Message,
    BotConfig,
    CreateChatBotData,
    CreateSessionData,
    CreateMessageData,
    CreateBotConfigData,
} from '@/types';

class BotService {
    // ChatBot methods
    /**
     * Получить все чат-боты
     */
    async getAllChatBots(): Promise<ChatBot[]> {
        const response = await api.get<any>('/bots');
        return response.data.bots || response.data;
    }

    /**
     * Получить конкретного чат-бота
     */
    async getChatBot(id: number): Promise<ChatBot> {
        const response = await api.get<any>(`/bots/${id}`);
        return response.data.bot || response.data;
    }

    /**
     * Создать чат-бота
     */
    async createChatBot(data: CreateChatBotData): Promise<ChatBot> {
        const response = await api.post<any>('/bots', data);
        return response.data.bot || response.data;
    }

    /**
     * Обновить чат-бота
     */
    async updateChatBot(id: number, data: Partial<CreateChatBotData>): Promise<ChatBot> {
        const response = await api.put<any>(`/bots/${id}`, data);
        return response.data.bot || response.data;
    }

    /**
     * Удалить чат-бота
     */
    async deleteChatBot(id: number): Promise<void> {
        await api.delete(`/bots/${id}`);
    }

    // BotSession methods
    /**
     * Получить все сессии чат-бота
     */
    async getBotSessions(chatBotId: number): Promise<BotSession[]> {
        const response = await api.get<any>(`/bots/${chatBotId}/sessions`);
        return response.data.sessions || response.data;
    }

    /**
     * Получить конкретную сессию
     */
    async getBotSession(sessionId: number): Promise<BotSession> {
        const response = await api.get<any>(`/sessions/${sessionId}`);
        return response.data.session || response.data;
    }

    /**
     * Создать сессию
     */
    async createSession(data: CreateSessionData): Promise<BotSession> {
        const response = await api.post<any>('/sessions', data);
        return response.data.session || response.data;
    }

    /**
     * Обновить сессию
     */
    async updateSession(id: number, data: Partial<BotSession>): Promise<BotSession> {
        const response = await api.put<any>(`/sessions/${id}`, data);
        return response.data.session || response.data;
    }

    /**
     * Удалить сессию
     */
    async deleteSession(id: number): Promise<void> {
        await api.delete(`/sessions/${id}`);
    }

    /**
     * Приостановить сессию
     */
    async pauseSession(id: number): Promise<BotSession> {
        const response = await api.post<any>(`/sessions/${id}/pause`);
        return response.data.session || response.data;
    }

    /**
     * Возобновить сессию
     */
    async resumeSession(id: number): Promise<BotSession> {
        const response = await api.post<any>(`/sessions/${id}/resume`);
        return response.data.session || response.data;
    }

    // Message methods
    /**
     * Получить сообщения сессии
     */
    async getSessionMessages(sessionId: number): Promise<Message[]> {
        const response = await api.get<any>(`/sessions/${sessionId}/messages`);
        return response.data.messages || response.data;
    }

    /**
     * Создать сообщение
     */
    async createMessage(data: CreateMessageData): Promise<Message> {
        const response = await api.post<any>('/messages', data);
        return response.data.message || response.data;
    }

    /**
     * Отправить сообщение (user message)
     */
    async sendUserMessage(sessionId: number, content: string): Promise<Message> {
        return this.createMessage({
            session_id: sessionId,
            content,
            sender: 'user',
        });
    }

    // BotConfig methods
    /**
     * Получить все конфигурации бота
     */
    async getBotConfigs(chatBotId: number): Promise<BotConfig[]> {
        const response = await api.get<any>(`/bots/${chatBotId}/configs`);
        return response.data.configs || response.data;
    }

    /**
     * Получить конкретную конфигурацию
     */
    async getBotConfig(configId: number): Promise<BotConfig> {
        const response = await api.get<any>(`/configs/${configId}`);
        return response.data.config || response.data;
    }

    /**
     * Создать конфигурацию
     */
    async createBotConfig(data: CreateBotConfigData): Promise<BotConfig> {
        const response = await api.post<any>('/configs', data);
        return response.data.config || response.data;
    }

    /**
     * Обновить конфигурацию
     */
    async updateBotConfig(id: number, data: Partial<CreateBotConfigData>): Promise<BotConfig> {
        const response = await api.put<any>(`/configs/${id}`, data);
        return response.data.config || response.data;
    }

    /**
     * Удалить конфигурацию
     */
    async deleteBotConfig(id: number): Promise<void> {
        await api.delete(`/configs/${id}`);
    }

    /**
     * Активировать конфигурацию
     */
    async activateConfig(id: number): Promise<BotConfig> {
        const response = await api.post<any>(`/configs/${id}/activate`);
        return response.data.config || response.data;
    }

    /**
     * Деактивировать конфигурацию
     */
    async deactivateConfig(id: number): Promise<BotConfig> {
        const response = await api.post<any>(`/configs/${id}/deactivate`);
        return response.data.config || response.data;
    }
}

export default new BotService();

