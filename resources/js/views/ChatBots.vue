<script setup lang="ts">
import { ref, onMounted, computed, watch, onBeforeUnmount } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import ChatBotCard from '@/components/ChatBotCard.vue';
import BotDialog from '@/components/BotDialog.vue';
import { useBotStore } from '@/stores/botStore';
import type { ChatBot } from '@/types';

const botStore = useBotStore();

const selectedBot = ref<ChatBot | null>(null);
const showCreateBotModal = ref(false);

const newBotForm = ref({
    chat_id: '',
    object_id: 0,
    bot_config_id: undefined as number | undefined,
});

const loading = computed(() => botStore.loading);
const error = computed(() => botStore.error);
const validBots = computed(() => botStore.chatBots.filter(bot => bot !== null && bot !== undefined));

onMounted(async () => {
  await botStore.fetchAllChatBots();
});

const selectBot = async (bot: ChatBot) => {
    try {
        selectedBot.value = bot;
        if (bot.messages) {
            botStore.messages = bot.messages;
        } else {
            const fullBot = await botStore.fetchChatBot(bot.chat_id);
            selectedBot.value = fullBot;
        }
    } catch (err) {
        console.error('[ChatBots] Failed to select bot:', err);
    }
};

const pollTimer = ref<number | null>(null);

watch(
    () => selectedBot.value?.chat_id,
    async (chatId) => {
        if (pollTimer.value) {
            clearInterval(pollTimer.value);
            pollTimer.value = null;
        }
        if (!chatId) return;

        try {
            const fullBot = await botStore.fetchChatBot(chatId);
            selectedBot.value = fullBot;
        } catch (e) {
            console.error('[ChatBots] Initial refresh failed:', e);
        }

        pollTimer.value = window.setInterval(async () => {
            try {
                const id = selectedBot.value?.chat_id;
                if (!id) return;
                const fullBot = await botStore.fetchChatBot(id);
                selectedBot.value = fullBot;
            } catch (e) {
            }
        }, 5000);
    }
);

onBeforeUnmount(() => {
    if (pollTimer.value) {
        clearInterval(pollTimer.value);
        pollTimer.value = null;
    }
});

const deleteBot = async (bot: ChatBot) => {
    if (confirm(`Вы уверены, что хотите остановить бота "${bot.chat_id}"?`)) {
        await botStore.deleteChatBot(bot.chat_id);
        
        await botStore.fetchAllChatBots();
        
        if (selectedBot.value?.chat_id === bot.chat_id) {
            const updatedBot = botStore.chatBots.find(b => b.chat_id === bot.chat_id);
            if (updatedBot) {
                selectedBot.value = updatedBot;
            }
        }
        
    }
};

const formatChatId = (phone: string): string => {
    if (!phone) return '';
    
    const digits = phone.replace(/\D/g, '');
    
    if (!phone.includes('@')) {
        return `${digits}@c.us`;
    }
    
    return phone;
};

const createBot = async () => {
    if (!newBotForm.value.chat_id || !newBotForm.value.object_id) {
        alert('Заполните все обязательные поля');
        return;
    }
    
    try {
        const formattedData = {
            ...newBotForm.value,
            chat_id: formatChatId(newBotForm.value.chat_id),
        };
        await botStore.createChatBot(formattedData);
        
        showCreateBotModal.value = false;
        newBotForm.value = { chat_id: '', object_id: 0, bot_config_id: undefined };
        
        await botStore.fetchAllChatBots();
    } catch (err) {
        console.error('[ChatBots] Failed to create bot:', err);
        alert('Ошибка создания бота: ' + (err as any)?.response?.data?.message || 'Неизвестная ошибка');
    }
};

const toggleBot = async (bot: ChatBot) => {
    try {
        if (bot.status === 'running') {
            await botStore.deleteChatBot(bot.chat_id);
        } else {
            await botStore.createChatBot({
                chat_id: bot.chat_id,
                object_id: bot.object_id,
                bot_config_id: bot.bot_config_id,
            });
        }
        
        await botStore.fetchAllChatBots();
        
        if (selectedBot.value?.chat_id === bot.chat_id) {
            const updatedBot = botStore.chatBots.find(b => b.chat_id === bot.chat_id);
            if (updatedBot) {
                selectedBot.value = updatedBot;
            }
        }
        
    } catch (err) {
        console.error('[ChatBots] Failed to toggle bot:', err);
        alert('Ошибка изменения статуса бота');
    }
};

const stopAllBots = async () => {
    if (confirm('Остановить всех ботов?')) {
        try {
            await botStore.stopAllBots();
            
            await botStore.fetchAllChatBots();
            
            if (selectedBot.value) {
                const updatedBot = botStore.chatBots.find(b => b.chat_id === selectedBot.value?.chat_id);
                if (updatedBot) {
                    selectedBot.value = updatedBot;
                }
            }
            
        } catch (err) {
            console.error('[ChatBots] Failed to stop all bots:', err);
        }
    }
};

const clearSession = async (bot: ChatBot) => {
    if (confirm(`Очистить контекст для "${bot.chat_id}"?\n\nВсе сообщения диалога будут удалены, но сессия останется активной. Бот начнёт новый диалог без истории переписки.`)) {
        try {
            // Очищаем контекст на сервере
            await botStore.clearBotSession(bot.chat_id);
            
            // Обновляем selectedBot с очищенными сообщениями
            if (selectedBot.value?.chat_id === bot.chat_id) {
                selectedBot.value = {
                    ...selectedBot.value,
                    messages: []
                };
            }
            
            // Даем небольшую задержку для синхронизации БД, затем принудительно перезагружаем
            setTimeout(async () => {
                try {
                    if (selectedBot.value?.chat_id === bot.chat_id) {
                        const freshBot = await botStore.fetchChatBot(bot.chat_id);
                        selectedBot.value = freshBot;
                    }
                } catch (e) {
                    console.error('[ChatBots] Failed to refresh after clear:', e);
                }
            }, 300);
            
            alert('Контекст сессии успешно очищен');
        } catch (err) {
            console.error('[ChatBots] Failed to clear session:', err);
            alert('Ошибка очистки контекста сессии');
        }
    }
};

const sendMessage = async (content: string) => {
    if (!selectedBot.value) return;
    
    try {
        botStore.messages.push({
            id: Date.now(),
            dialog_id: (botStore as any).currentChatBot?.dialog_id,
            role: 'user',
            content,
            tokens_in: null as any,
            tokens_out: null as any,
            meta: {},
            created_at: new Date().toISOString(),
        } as any);
    } catch (err) {
        console.error('[ChatBots] Failed to send message:', err);
    }
};

</script>

<template>
  <MainLayout>
    <div class="chat-bots-page">
      <div class="page-header">
        <div>
          <h1>Чат боты</h1>
          <p>Управление чат-ботами и их сессиями</p>
        </div>
        <div class="page-header__actions">
          <button 
            class="btn btn--danger"
            @click="stopAllBots"
          >
            ⏸️ Остановить всех
          </button>
          <button 
            class="btn btn--primary"
            @click="showCreateBotModal = true"
          >
            + Создать бота
          </button>
        </div>
      </div>

      <div v-if="error" class="alert alert--danger">
        {{ error }}
      </div>

      <div class="chat-bots-content">
        <div class="bots-section">
          <h2>Список ботов</h2>
          <div class="bots-grid">
            <ChatBotCard
              v-for="bot in validBots"
              :key="bot.chat_id"
              :bot="bot"
              :selected="selectedBot?.chat_id === bot.chat_id"
              @select="selectBot"
              @edit="() => {}"
              @delete="deleteBot"
              @toggle="toggleBot"
            />
          </div>
        </div>

        <div v-if="selectedBot" class="chat-section">
          <div class="chat-header">
            <div class="chat-header__info">
              <h2>{{ selectedBot.chat_id }}</h2>
              <span class="chat-platform">{{ selectedBot.platform }}</span>
            </div>
            <div class="chat-header__actions">
              <button 
                v-if="selectedBot.status === 'running'"
                class="btn btn--warning btn--sm"
                @click="clearSession(selectedBot)"
                title="Очистить контекст сессии"
              >
                🧹 Очистить контекст
              </button>
            </div>
          </div>
          <BotDialog
            :messages="botStore.messages"
            :loading="loading"
            @send="sendMessage"
          />
        </div>

        <div v-else class="empty-chat">
          <p>Выберите бота для начала диалога</p>
        </div>
      </div>

      <div v-if="showCreateBotModal" class="modal-overlay" @click.self="showCreateBotModal = false">
        <div class="modal" @click.stop>
          <div class="modal__header">
            <h3>Создать чат-бота</h3>
            <button class="btn btn--ghost btn--sm" @click="showCreateBotModal = false">✕</button>
          </div>
          <div class="modal__body">
            <div class="form-group">
              <label class="form-label">Номер WhatsApp *</label>
              <input
                v-model="newBotForm.chat_id"
                type="text"
                class="form-input"
                placeholder="79001234567"
              />
              <small class="form-help">Введите номер без @c.us — он добавится автоматически</small>
            </div>
            <div class="form-group">
              <label class="form-label">ID объекта *</label>
              <input
                v-model.number="newBotForm.object_id"
                type="number"
                class="form-input"
                placeholder="508437"
              />
            </div>
          </div>
          <div class="modal__footer">
            <button class="btn btn--ghost" @click="showCreateBotModal = false">Отмена</button>
            <button class="btn btn--primary" @click="createBot" :disabled="loading">
              Создать
            </button>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>
