import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import botService from '@/services/botService';
import type { ChatBot, BotSession, Message, BotConfig } from '@/types';

export const useBotStore = defineStore('bot', () => {
    // State
    const chatBots = ref<ChatBot[]>([]);
    const currentChatBot = ref<ChatBot | null>(null);
    const sessions = ref<BotSession[]>([]);
    const currentSession = ref<BotSession | null>(null);
    const messages = ref<Message[]>([]);
    const configs = ref<BotConfig[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    // Getters
    const activeChatBots = computed(() => 
        chatBots.value.filter(bot => bot.status === 'online')
    );

    const activeSessions = computed(() => 
        sessions.value.filter(session => session.status === 'active')
    );

    const activeConfig = computed(() => 
        configs.value.find(config => config.is_active)
    );

    // ChatBot Actions
    async function fetchAllChatBots() {
        try {
            loading.value = true;
            error.value = null;
            chatBots.value = await botService.getAllChatBots();
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка загрузки чат-ботов';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function fetchChatBot(id: number) {
        try {
            loading.value = true;
            error.value = null;
            currentChatBot.value = await botService.getChatBot(id);
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка загрузки чат-бота';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function createChatBot(data: any) {
        try {
            loading.value = true;
            error.value = null;
            const newBot = await botService.createChatBot(data);
            chatBots.value.push(newBot);
            return newBot;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка создания чат-бота';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function updateChatBot(id: number, data: any) {
        try {
            loading.value = true;
            error.value = null;
            const updatedBot = await botService.updateChatBot(id, data);
            const index = chatBots.value.findIndex(bot => bot.id === id);
            if (index !== -1) {
                chatBots.value[index] = updatedBot;
            }
            if (currentChatBot.value?.id === id) {
                currentChatBot.value = updatedBot;
            }
            return updatedBot;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка обновления чат-бота';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function deleteChatBot(id: number) {
        try {
            loading.value = true;
            error.value = null;
            await botService.deleteChatBot(id);
            chatBots.value = chatBots.value.filter(bot => bot.id !== id);
            if (currentChatBot.value?.id === id) {
                currentChatBot.value = null;
            }
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка удаления чат-бота';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // Session Actions
    async function fetchBotSessions(chatBotId: number) {
        try {
            loading.value = true;
            error.value = null;
            sessions.value = await botService.getBotSessions(chatBotId);
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка загрузки сессий';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function fetchSession(sessionId: number) {
        try {
            loading.value = true;
            error.value = null;
            currentSession.value = await botService.getBotSession(sessionId);
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка загрузки сессии';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function createSession(data: any) {
        try {
            loading.value = true;
            error.value = null;
            const newSession = await botService.createSession(data);
            sessions.value.push(newSession);
            return newSession;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка создания сессии';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function pauseSession(id: number) {
        try {
            loading.value = true;
            error.value = null;
            const updatedSession = await botService.pauseSession(id);
            const index = sessions.value.findIndex(session => session.id === id);
            if (index !== -1) {
                sessions.value[index] = updatedSession;
            }
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка приостановки сессии';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function resumeSession(id: number) {
        try {
            loading.value = true;
            error.value = null;
            const updatedSession = await botService.resumeSession(id);
            const index = sessions.value.findIndex(session => session.id === id);
            if (index !== -1) {
                sessions.value[index] = updatedSession;
            }
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка возобновления сессии';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // Message Actions
    async function fetchSessionMessages(sessionId: number) {
        try {
            loading.value = true;
            error.value = null;
            messages.value = await botService.getSessionMessages(sessionId);
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка загрузки сообщений';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function sendMessage(sessionId: number, content: string) {
        try {
            loading.value = true;
            error.value = null;
            const newMessage = await botService.sendUserMessage(sessionId, content);
            messages.value.push(newMessage);
            return newMessage;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка отправки сообщения';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // Config Actions
    async function fetchBotConfigs(chatBotId: number) {
        try {
            loading.value = true;
            error.value = null;
            configs.value = await botService.getBotConfigs(chatBotId);
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка загрузки конфигураций';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function createBotConfig(data: any) {
        try {
            loading.value = true;
            error.value = null;
            const newConfig = await botService.createBotConfig(data);
            configs.value.push(newConfig);
            return newConfig;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка создания конфигурации';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function updateBotConfig(id: number, data: any) {
        try {
            loading.value = true;
            error.value = null;
            const updatedConfig = await botService.updateBotConfig(id, data);
            const index = configs.value.findIndex(config => config.id === id);
            if (index !== -1) {
                configs.value[index] = updatedConfig;
            }
            return updatedConfig;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка обновления конфигурации';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function deleteBotConfig(id: number) {
        try {
            loading.value = true;
            error.value = null;
            await botService.deleteBotConfig(id);
            configs.value = configs.value.filter(config => config.id !== id);
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка удаления конфигурации';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function activateConfig(id: number) {
        try {
            loading.value = true;
            error.value = null;
            const updatedConfig = await botService.activateConfig(id);
            // Деактивируем другие конфигурации
            configs.value = configs.value.map(config => ({
                ...config,
                is_active: config.id === id,
            }));
            return updatedConfig;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка активации конфигурации';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    function clearError() {
        error.value = null;
    }

    function clearCurrentData() {
        currentChatBot.value = null;
        currentSession.value = null;
        messages.value = [];
        configs.value = [];
    }

    return {
        // State
        chatBots,
        currentChatBot,
        sessions,
        currentSession,
        messages,
        configs,
        loading,
        error,
        
        // Getters
        activeChatBots,
        activeSessions,
        activeConfig,
        
        // Actions
        fetchAllChatBots,
        fetchChatBot,
        createChatBot,
        updateChatBot,
        deleteChatBot,
        fetchBotSessions,
        fetchSession,
        createSession,
        pauseSession,
        resumeSession,
        fetchSessionMessages,
        sendMessage,
        fetchBotConfigs,
        createBotConfig,
        updateBotConfig,
        deleteBotConfig,
        activateConfig,
        clearError,
        clearCurrentData,
    };
});

