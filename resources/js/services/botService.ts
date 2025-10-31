import api from './api';
import type {
    ChatBot,
    BotSession,
    Message,
    BotConfig,
    CreateChatBotData,
    CreateSessionData,
} from '@/types';

class BotService {
    // Bot Sessions (ChatBots) - основная работа с ботами
    /**
     * Получить все сессии ботов
     */
    async getAllChatBots(): Promise<ChatBot[]> {
        const response = await api.get('/bots');
        return response.data.data || [];
    }

    /**
     * Получить конкретного бота по chatId
     */
    async getChatBot(chatId: string): Promise<ChatBot> {
        const response = await api.get(`/bots/${chatId}`);
        return response.data.data;
    }

    /**
     * Создать/запустить бота
     */
    async startBot(data: CreateSessionData): Promise<ChatBot> {
        const response = await api.post('/bots/start', data);
        return response.data.data;
    }

    /**
     * Остановить конкретного бота
     */
    async stopBot(chatId: string): Promise<ChatBot> {
        const response = await api.delete(`/bots/${chatId}`);
        return response.data.data;
    }

    /**
     * Остановить всех ботов
     */
    async stopAllBots(): Promise<{ message: string; count: number }> {
        const response = await api.post('/bots/stop-all');
        return response.data;
    }

    // Bot Config methods
    /**
     * Получить все конфигурации (с фильтром по платформе)
     */
    async getBotConfigs(platform?: 'whatsapp'): Promise<BotConfig[]> {
        const params = platform ? { platform } : {};
        const response = await api.get('/bot-configs', { params });
        return response.data.data || [];
    }

    /**
     * Получить конкретную конфигурацию
     */
    async getBotConfig(id: number): Promise<BotConfig> {
        const response = await api.get(`/bot-configs/${id}`);
        return response.data.data;
    }

    // Получение активной конфигурации больше не требуется

    /**
     * Создать конфигурацию
     */
    async createBotConfig(data: Partial<BotConfig>): Promise<BotConfig> {
        const response = await api.post('/bot-configs', data);
        return response.data.data;
    }

    /**
     * Обновить конфигурацию
     */
    async updateBotConfig(id: number, data: Partial<BotConfig>): Promise<BotConfig> {
        const response = await api.put(`/bot-configs/${id}`, data);
        return response.data.data;
    }

    /**
     * Удалить конфигурацию
     */
    async deleteBotConfig(id: number): Promise<void> {
        await api.delete(`/bot-configs/${id}`);
    }

    // Активация конфигурации больше не используется
}

export default new BotService();
