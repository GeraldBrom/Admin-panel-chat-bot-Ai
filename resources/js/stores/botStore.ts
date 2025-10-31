import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import botService from '@/services/botService';
import type { ChatBot, BotConfig, Message } from '@/types';

export const useBotStore = defineStore('bot', () => {
    // State
    const chatBots = ref<ChatBot[]>([]);
    const currentChatBot = ref<ChatBot | null>(null);
    const messages = ref<Message[]>([]);
    const configs = ref<BotConfig[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    // Getters
    const activeChatBots = computed(() => 
        chatBots.value.filter(bot => bot.status === 'running')
    );

    // Активная конфигурация больше не используется — конфиг выбирается явно

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

    async function fetchChatBot(chatId: string) {
        try {
            loading.value = true;
            error.value = null;
            const bot = await botService.getChatBot(chatId);
            currentChatBot.value = bot;
            
            // Update messages if available
            if (bot.messages) {
                messages.value = bot.messages;
            }
            
            return bot;
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
            const newBot = await botService.startBot(data);
            chatBots.value.push(newBot);
            return newBot;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка создания чат-бота';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function updateChatBot(chatId: string, data: any) {
        // Update через API пока нет, можно добавить позже
        try {
            loading.value = true;
            error.value = null;
            const index = chatBots.value.findIndex(bot => bot.chat_id === chatId);
            if (index !== -1) {
                chatBots.value[index] = { ...chatBots.value[index], ...data };
            }
            if (currentChatBot.value?.chat_id === chatId) {
                currentChatBot.value = { ...currentChatBot.value, ...data };
            }
            return chatBots.value[index];
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка обновления чат-бота';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function deleteChatBot(chatId: string) {
        try {
            loading.value = true;
            error.value = null;
            await botService.stopBot(chatId);
            chatBots.value = chatBots.value.filter(bot => bot.chat_id !== chatId);
            if (currentChatBot.value?.chat_id === chatId) {
                currentChatBot.value = null;
            }
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка удаления чат-бота';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function stopAllBots() {
        try {
            loading.value = true;
            error.value = null;
            await botService.stopAllBots();
            chatBots.value = [];
            currentChatBot.value = null;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка остановки ботов';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    // Config Actions
    async function fetchBotConfigs(platform?: 'whatsapp') {
        try {
            loading.value = true;
            error.value = null;
            configs.value = await botService.getBotConfigs(platform);
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

    // Активация конфигурации не требуется

    function clearError() {
        error.value = null;
    }

    function clearCurrentData() {
        currentChatBot.value = null;
        messages.value = [];
    }

    return {
        // State
        chatBots,
        currentChatBot,
        messages,
        configs,
        loading,
        error,
        
        // Getters
        activeChatBots,
        
        
        // Actions
        fetchAllChatBots,
        fetchChatBot,
        createChatBot,
        updateChatBot,
        deleteChatBot,
        stopAllBots,
        fetchBotConfigs,
        createBotConfig,
        updateBotConfig,
        deleteBotConfig,
        
        clearError,
        clearCurrentData,
    };
});
