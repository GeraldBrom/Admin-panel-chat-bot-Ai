import { defineStore } from 'pinia';
import { ref, computed } from 'vue';
import botService from '@/services/botService';
import type { ChatBot, BotConfig, Message } from '@/types';

export const useBotStore = defineStore('bot', () => {
    const chatBots = ref<ChatBot[]>([]);
    const currentChatBot = ref<ChatBot | null>(null);
    const messages = ref<Message[]>([]);
    const configs = ref<BotConfig[]>([]);
    const loading = ref(false);
    const error = ref<string | null>(null);

    const activeChatBots = computed(() => 
        chatBots.value.filter(bot => bot.status === 'running')
    );

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
            
            const existingIndex = chatBots.value.findIndex(bot => bot.chat_id === newBot.chat_id);
            if (existingIndex !== -1) {
                chatBots.value[existingIndex] = newBot;
            } else {
            chatBots.value.push(newBot);
            }
            
            return newBot;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка создания чат-бота';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function updateChatBot(chatId: string, data: any) {
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
            const stoppedBot = await botService.stopBot(chatId);
            
            const index = chatBots.value.findIndex(bot => bot.chat_id === chatId);
            if (index !== -1) {
                chatBots.value[index] = stoppedBot;
            }
            
            if (currentChatBot.value?.chat_id === chatId) {
                currentChatBot.value = stoppedBot;
            }
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка остановки чат-бота';
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
            
            chatBots.value = chatBots.value.map(bot => ({
                ...bot,
                status: 'stopped' as const,
            }));
            
            if (currentChatBot.value) {
                currentChatBot.value = {
                    ...currentChatBot.value,
                    status: 'stopped' as const,
                };
            }
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка остановки ботов';
            throw err;
        } finally {
            loading.value = false;
        }
    }

    async function clearBotSession(chatId: string) {
        try {
            loading.value = true;
            error.value = null;
            const updatedBot = await botService.clearSession(chatId);
            
            // Проверяем, что updatedBot не null/undefined
            if (updatedBot) {
                const index = chatBots.value.findIndex(bot => bot.chat_id === chatId);
                if (index !== -1) {
                    chatBots.value[index] = updatedBot;
                }
                
                // Всегда очищаем messages для выбранного бота
                if (currentChatBot.value?.chat_id === chatId) {
                    currentChatBot.value = updatedBot;
                }
            }
            
            // Принудительно очищаем messages независимо от currentChatBot
            messages.value = [];
            
            return updatedBot;
        } catch (err: any) {
            error.value = err.response?.data?.message || 'Ошибка очистки контекста сессии';
            throw err;
        } finally {
            loading.value = false;
        }
    }

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


    function clearError() {
        error.value = null;
    }

    function clearCurrentData() {
        currentChatBot.value = null;
        messages.value = [];
    }

    return {
        chatBots,
        currentChatBot,
        messages,
        configs,
        loading,
        error,
        
        activeChatBots,
        
        
        fetchAllChatBots,
        fetchChatBot,
        createChatBot,
        updateChatBot,
        deleteChatBot,
        stopAllBots,
        clearBotSession,
        fetchBotConfigs,
        createBotConfig,
        updateBotConfig,
        deleteBotConfig,
        
        clearError,
        clearCurrentData,
    };
});
