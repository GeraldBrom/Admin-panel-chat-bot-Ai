import api from './api';
import type {
    ScenarioBot,
    ScenarioStep,
    ScenarioBotSession,
    CreateScenarioBotData,
    UpdateScenarioBotData,
    CreateScenarioStepData,
    UpdateScenarioStepData,
    StartScenarioBotSessionData,
    UpdateStepOrderData,
    UpdateStepPositionsData,
} from '@/types';

class ScenarioBotService {
    // === CRUD для сценарных ботов ===

    /**
     * Получить все сценарные боты
     */
    async getAllScenarioBots(platform?: 'whatsapp' | 'telegram' | 'max'): Promise<ScenarioBot[]> {
        const params = platform ? { platform } : {};
        const response = await api.get('/scenario-bots', { params });
        return response.data.data || [];
    }

    /**
     * Получить конкретного сценарного бота
     */
    async getScenarioBot(id: number): Promise<ScenarioBot> {
        const response = await api.get(`/scenario-bots/${id}`);
        return response.data.data;
    }

    /**
     * Создать сценарного бота
     */
    async createScenarioBot(data: CreateScenarioBotData): Promise<ScenarioBot> {
        const response = await api.post('/scenario-bots', data);
        return response.data.data;
    }

    /**
     * Обновить сценарного бота
     */
    async updateScenarioBot(id: number, data: UpdateScenarioBotData): Promise<ScenarioBot> {
        const response = await api.put(`/scenario-bots/${id}`, data);
        return response.data.data;
    }

    /**
     * Удалить сценарного бота
     */
    async deleteScenarioBot(id: number): Promise<void> {
        await api.delete(`/scenario-bots/${id}`);
    }

    // === Управление шагами сценария ===

    /**
     * Получить все шаги для бота
     */
    async getSteps(botId: number): Promise<ScenarioStep[]> {
        const response = await api.get(`/scenario-bots/${botId}/steps`);
        return response.data.data || [];
    }

    /**
     * Получить конкретный шаг
     */
    async getStep(botId: number, stepId: number): Promise<ScenarioStep> {
        const response = await api.get(`/scenario-bots/${botId}/steps/${stepId}`);
        return response.data.data;
    }

    /**
     * Создать шаг
     */
    async createStep(botId: number, data: CreateScenarioStepData): Promise<ScenarioStep> {
        const response = await api.post(`/scenario-bots/${botId}/steps`, data);
        return response.data.data;
    }

    /**
     * Обновить шаг
     */
    async updateStep(botId: number, stepId: number, data: UpdateScenarioStepData): Promise<ScenarioStep> {
        const response = await api.put(`/scenario-bots/${botId}/steps/${stepId}`, data);
        return response.data.data;
    }

    /**
     * Удалить шаг
     */
    async deleteStep(botId: number, stepId: number): Promise<void> {
        await api.delete(`/scenario-bots/${botId}/steps/${stepId}`);
    }

    /**
     * Обновить порядок шагов
     */
    async updateStepsOrder(botId: number, data: UpdateStepOrderData): Promise<void> {
        await api.post(`/scenario-bots/${botId}/steps/update-order`, data);
    }

    /**
     * Обновить позиции шагов (для визуального редактора)
     */
    async updateStepsPositions(botId: number, data: UpdateStepPositionsData): Promise<void> {
        await api.post(`/scenario-bots/${botId}/steps/update-positions`, data);
    }

    // === Управление сессиями ===

    /**
     * Запустить сессию сценарного бота
     */
    async startSession(data: StartScenarioBotSessionData): Promise<ScenarioBotSession> {
        const response = await api.post('/scenario-bots/sessions/start', data);
        return response.data.data;
    }

    /**
     * Остановить сессию
     */
    async stopSession(chatId: string): Promise<void> {
        await api.delete(`/scenario-bots/sessions/${chatId}/stop`);
    }

    /**
     * Сбросить сессию (вернуть на начало)
     */
    async resetSession(chatId: string): Promise<void> {
        await api.post(`/scenario-bots/sessions/${chatId}/reset`);
    }

    /**
     * Получить конкретную сессию
     */
    async getSession(chatId: string): Promise<ScenarioBotSession> {
        const response = await api.get(`/scenario-bots/sessions/${chatId}`);
        return response.data.data;
    }

    /**
     * Получить все сессии для бота
     */
    async getBotSessions(botId: number): Promise<ScenarioBotSession[]> {
        const response = await api.get(`/scenario-bots/${botId}/sessions`);
        return response.data.data || [];
    }
}

export default new ScenarioBotService();

