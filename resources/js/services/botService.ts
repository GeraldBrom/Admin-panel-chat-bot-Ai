import api from './api';
import type {
    ChatBot,
    BotConfig,
    CreateSessionData,
} from '@/types';

class BotService {
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

    /**
     * Очистить контекст активной сессии (удалить все сообщения)
     */
    async clearSession(chatId: string): Promise<ChatBot> {
        const response = await api.delete(`/bots/${chatId}/session`);
        return response.data.data;
    }

    /**
     * Получить все конфигурации (с фильтром по платформе)
     */
    async getBotConfigs(platform?: 'whatsapp'): Promise<BotConfig[]> {
        const params = platform ? { platform } : {};
        const response = await api.get('/bot-configs', { params });
        return response.data.data || [];
    }


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
}

export default new BotService();
