import { defineStore } from 'pinia';
import { ref } from 'vue';
import scenarioBotService from '@/services/scenarioBotService';
import type {
    ScenarioBot,
    ScenarioStep,
    ScenarioBotSession,
    CreateScenarioBotData,
    UpdateScenarioBotData,
    CreateScenarioStepData,
    UpdateScenarioStepData,
} from '@/types';

export const useScenarioBotStore = defineStore('scenarioBot', () => {
    // State
    const scenarioBots = ref<ScenarioBot[]>([]);
    const currentBot = ref<ScenarioBot | null>(null);
    const currentSteps = ref<ScenarioStep[]>([]);
    const sessions = ref<ScenarioBotSession[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    // === Действия для ботов ===

    /**
     * Получить все сценарные боты
     */
    async function fetchAllScenarioBots(platform?: 'whatsapp' | 'telegram' | 'max') {
        try {
            loading.value = true;
            error.value = null;
            scenarioBots.value = await scenarioBotService.getAllScenarioBots(platform);
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка загрузки сценарных ботов';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Получить конкретного бота
     */
    async function fetchScenarioBot(id: number) {
        try {
            loading.value = true;
            error.value = null;
            currentBot.value = await scenarioBotService.getScenarioBot(id);
            if (currentBot.value.steps) {
                currentSteps.value = currentBot.value.steps;
            }
            return currentBot.value;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка загрузки сценарного бота';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Создать сценарного бота
     */
    async function createScenarioBot(data: CreateScenarioBotData) {
        try {
            loading.value = true;
            error.value = null;
            const newBot = await scenarioBotService.createScenarioBot(data);
            scenarioBots.value.push(newBot);
            return newBot;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка создания сценарного бота';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Обновить сценарного бота
     */
    async function updateScenarioBot(id: number, data: UpdateScenarioBotData) {
        try {
            loading.value = true;
            error.value = null;
            const updatedBot = await scenarioBotService.updateScenarioBot(id, data);
            
            // Обновляем в списке
            const index = scenarioBots.value.findIndex(bot => bot.id === id);
            if (index !== -1) {
                scenarioBots.value[index] = updatedBot;
            }
            
            // Обновляем текущего бота
            if (currentBot.value?.id === id) {
                currentBot.value = updatedBot;
            }
            
            return updatedBot;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка обновления сценарного бота';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Удалить сценарного бота
     */
    async function deleteScenarioBot(id: number) {
        try {
            loading.value = true;
            error.value = null;
            await scenarioBotService.deleteScenarioBot(id);
            scenarioBots.value = scenarioBots.value.filter(bot => bot.id !== id);
            
            if (currentBot.value?.id === id) {
                currentBot.value = null;
                currentSteps.value = [];
            }
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка удаления сценарного бота';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // === Действия для шагов ===

    /**
     * Получить шаги для бота
     */
    async function fetchSteps(botId: number) {
        try {
            loading.value = true;
            error.value = null;
            currentSteps.value = await scenarioBotService.getSteps(botId);
            return currentSteps.value;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка загрузки шагов';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Создать шаг
     */
    async function createStep(botId: number, data: CreateScenarioStepData) {
        try {
            loading.value = true;
            error.value = null;
            const newStep = await scenarioBotService.createStep(botId, data);
            currentSteps.value.push(newStep);
            return newStep;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка создания шага';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Обновить шаг
     */
    async function updateStep(botId: number, stepId: number, data: UpdateScenarioStepData) {
        try {
            loading.value = true;
            error.value = null;
            const updatedStep = await scenarioBotService.updateStep(botId, stepId, data);
            
            const index = currentSteps.value.findIndex(step => step.id === stepId);
            if (index !== -1) {
                currentSteps.value[index] = updatedStep;
            }
            
            return updatedStep;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка обновления шага';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Удалить шаг
     */
    async function deleteStep(botId: number, stepId: number) {
        try {
            loading.value = true;
            error.value = null;
            await scenarioBotService.deleteStep(botId, stepId);
            currentSteps.value = currentSteps.value.filter(step => step.id !== stepId);
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка удаления шага';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Обновить порядок шагов
     */
    async function updateStepsOrder(botId: number, steps: Array<{ id: number; order: number }>) {
        try {
            loading.value = true;
            error.value = null;
            await scenarioBotService.updateStepsOrder(botId, { steps });
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка обновления порядка шагов';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Обновить позиции шагов
     */
    async function updateStepsPositions(botId: number, steps: Array<{ id: number; position_x: number; position_y: number }>) {
        try {
            loading.value = true;
            error.value = null;
            await scenarioBotService.updateStepsPositions(botId, { steps });
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка обновления позиций шагов';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // === Действия для сессий ===

    /**
     * Запустить сессию
     */
    async function startSession(chatId: string, scenarioBotId: number, objectId?: number, platform: 'whatsapp' | 'telegram' | 'max' = 'whatsapp') {
        try {
            loading.value = true;
            error.value = null;
            const session = await scenarioBotService.startSession({
                scenario_bot_id: scenarioBotId,
                chat_id: chatId,
                object_id: objectId,
                platform,
            });
            sessions.value.push(session);
            return session;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка запуска сессии';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Остановить сессию
     */
    async function stopSession(chatId: string) {
        try {
            loading.value = true;
            error.value = null;
            await scenarioBotService.stopSession(chatId);
            
            const session = sessions.value.find(s => s.chat_id === chatId);
            if (session) {
                session.status = 'stopped';
                session.stopped_at = new Date().toISOString();
            }
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка остановки сессии';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Сбросить сессию
     */
    async function resetSession(chatId: string) {
        try {
            loading.value = true;
            error.value = null;
            await scenarioBotService.resetSession(chatId);
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка сброса сессии';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Получить сессии для бота
     */
    async function fetchBotSessions(botId: number) {
        try {
            loading.value = true;
            error.value = null;
            sessions.value = await scenarioBotService.getBotSessions(botId);
            return sessions.value;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка загрузки сессий';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Получить конкретную сессию
     */
    async function getSession(chatId: string) {
        try {
            loading.value = true;
            error.value = null;
            const session = await scenarioBotService.getSession(chatId);
            
            // Обновляем в списке сессий
            const index = sessions.value.findIndex(s => s.chat_id === chatId);
            if (index !== -1) {
                sessions.value[index] = session;
            }
            
            return session;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка загрузки сессии';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    /**
     * Очистить ошибку
     */
    function clearError() {
        error.value = null;
    }

    /**
     * Очистить текущие данные
     */
    function clearCurrentData() {
        currentBot.value = null;
        currentSteps.value = [];
        sessions.value = [];
    }

    return {
        // State
        scenarioBots,
        currentBot,
        currentSteps,
        sessions,
        loading,
        error,

        // Actions
        fetchAllScenarioBots,
        fetchScenarioBot,
        createScenarioBot,
        updateScenarioBot,
        deleteScenarioBot,
        fetchSteps,
        createStep,
        updateStep,
        deleteStep,
        updateStepsOrder,
        updateStepsPositions,
        startSession,
        stopSession,
        resetSession,
        fetchBotSessions,
        getSession,
        clearError,
        clearCurrentData,
    };
});

