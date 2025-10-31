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

// Load bots on mount
onMounted(async () => {
    console.log('[ChatBots] onMounted: fetching all chat bots...');
    try {
    await botStore.fetchAllChatBots();
        console.log('[ChatBots] Bots loaded:', botStore.chatBots);
    } catch (e) {
        console.error('[ChatBots] Failed to load bots on mount:', e);
    }
});

// Select bot and load its messages
const selectBot = async (bot: ChatBot) => {
    try {
        console.log('[ChatBots] Selecting bot:', bot);
        selectedBot.value = bot;
        // Если у бота уже есть сообщения, используем их
        if (bot.messages) {
            botStore.messages = bot.messages;
            console.log('[ChatBots] Using cached messages for bot:', bot.chat_id, 'count:', bot.messages.length);
        } else {
            // Загружаем бота с сообщениями с сервера
            console.log('[ChatBots] Fetching full bot with messages for:', bot.chat_id);
            const fullBot = await botStore.fetchChatBot(bot.chat_id);
            selectedBot.value = fullBot;
            console.log('[ChatBots] Full bot loaded:', {
                chat_id: fullBot.chat_id,
                messagesCount: fullBot.messages?.length ?? 0,
            });
        }
    } catch (err) {
        console.error('[ChatBots] Failed to load bot messages:', err);
    }
};

// Polling for messages of selected bot
const pollTimer = ref<number | null>(null);

watch(
    () => selectedBot.value?.chat_id,
    async (chatId) => {
        // Clear previous timer
        if (pollTimer.value) {
            clearInterval(pollTimer.value);
            pollTimer.value = null;
        }
        if (!chatId) return;

        // Immediate refresh once on select
        try {
            console.log('[ChatBots] Initial refresh messages for:', chatId);
            const fullBot = await botStore.fetchChatBot(chatId);
            selectedBot.value = fullBot;
        } catch (e) {
            console.error('[ChatBots] Initial refresh failed:', e);
        }

        // Start polling every 5s
        pollTimer.value = window.setInterval(async () => {
            try {
                const id = selectedBot.value?.chat_id;
                if (!id) return;
                const fullBot = await botStore.fetchChatBot(id);
                // keep same selectedBot reference fresh
                selectedBot.value = fullBot;
            } catch (e) {
                // swallow errors to keep polling
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

// Handle delete bot
const deleteBot = async (bot: ChatBot) => {
    if (confirm(`Вы уверены, что хотите остановить бота "${bot.chat_id}"?`)) {
        console.log('[ChatBots] Stopping bot:', bot.chat_id);
        await botStore.deleteChatBot(bot.chat_id);
        if (selectedBot.value?.chat_id === bot.chat_id) {
            selectedBot.value = null;
        }
        console.log('[ChatBots] Bot stopped:', bot.chat_id);
    }
};

// Format chat ID for WhatsApp
const formatChatId = (phone: string): string => {
    if (!phone) return '';
    
    // Remove all non-digit characters
    const digits = phone.replace(/\D/g, '');
    
    // Add @c.us suffix if not present
    if (!phone.includes('@')) {
        return `${digits}@c.us`;
    }
    
    return phone;
};

// Create bot
const createBot = async () => {
    if (!newBotForm.value.chat_id || !newBotForm.value.object_id) {
        alert('Заполните все обязательные поля');
        return;
    }
    
    try {
        // Format chat_id for WhatsApp
        const formattedData = {
            ...newBotForm.value,
            chat_id: formatChatId(newBotForm.value.chat_id),
        };
        console.log('[ChatBots] Creating bot with data:', formattedData);
        await botStore.createChatBot(formattedData);
        console.log('[ChatBots] Bot created successfully:', formattedData.chat_id);
        showCreateBotModal.value = false;
        newBotForm.value = { chat_id: '', object_id: 0, bot_config_id: undefined };
    } catch (err) {
        console.error('[ChatBots] Failed to create bot:', err);
    }
};

// Toggle bot status
const toggleBot = async (bot: ChatBot) => {
    try {
        if (bot.status === 'running') {
            console.log('[ChatBots] Toggling bot to stop:', bot.chat_id);
            await botStore.deleteChatBot(bot.chat_id);
            console.log('[ChatBots] Bot stopped via toggle:', bot.chat_id);
        } else {
            console.log('[ChatBots] Toggling bot to start:', bot.chat_id);
            await botStore.createChatBot({
                chat_id: bot.chat_id,
                object_id: bot.object_id,
                bot_config_id: bot.bot_config_id,
            });
            console.log('[ChatBots] Bot started via toggle:', bot.chat_id);
        }
    } catch (err) {
        console.error('[ChatBots] Failed to toggle bot:', err);
    }
};

// Stop all bots
const stopAllBots = async () => {
    if (confirm('Остановить всех ботов?')) {
        try {
            console.log('[ChatBots] Stopping all bots...');
            await botStore.stopAllBots();
            console.log('[ChatBots] All bots stopped');
        } catch (err) {
            console.error('[ChatBots] Failed to stop all bots:', err);
        }
    }
};

// Send message
const sendMessage = async (content: string) => {
    if (!selectedBot.value) return;
    
    try {
        // Отправляем сообщение напрямую в чат с ботом
        // await botStore.sendMessage(selectedBot.value.id, content);
        console.log('[ChatBots] Sending message', {
            to: selectedBot.value.chat_id,
            content,
        });
        // Локально добавим в ленту, чтобы UI сразу отобразил
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

// Watch for incoming/updated messages to log what we receive
watch(
    () => botStore.messages,
    (newMessages, oldMessages) => {
        const oldCount = Array.isArray(oldMessages) ? oldMessages.length : 0;
        const newCount = Array.isArray(newMessages) ? newMessages.length : 0;
        console.log('[ChatBots] Messages updated', {
            chat_id: selectedBot.value?.chat_id,
            oldCount,
            newCount,
            lastMessage: newCount > 0 ? newMessages[newCount - 1] : null,
        });
    },
    { deep: true }
);
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

      <!-- Error message -->
      <div v-if="error" class="alert alert--danger">
        {{ error }}
      </div>

      <!-- Main content -->
      <div class="chat-bots-content">
        <!-- Bots list -->
        <div class="bots-section">
          <h2>Список ботов</h2>
          <div class="bots-grid">
            <ChatBotCard
              v-for="bot in botStore.chatBots"
              :key="bot.id"
              :bot="bot"
              :selected="selectedBot?.chat_id === bot.chat_id"
              @select="selectBot"
              @edit="() => {}"
              @delete="deleteBot"
              @toggle="toggleBot"
            />
          </div>
        </div>

        <!-- Chat section -->
        <div v-if="selectedBot" class="chat-section">
          <div class="chat-header">
            <div class="chat-header__info">
              <h2>{{ selectedBot.chat_id }}</h2>
              <span class="chat-platform">{{ selectedBot.platform }}</span>
            </div>
          </div>
          <BotDialog
            :messages="botStore.messages"
            :loading="loading"
            @send="sendMessage"
          />
        </div>

        <!-- Empty state -->
        <div v-else class="empty-chat">
          <p>Выберите бота для начала диалога</p>
        </div>
      </div>

      <!-- Create bot modal -->
      <div v-if="showCreateBotModal" class="modal-overlay" @click="showCreateBotModal = false">
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

<style scoped lang="scss">
.chat-bots-page {
  overflow-x: hidden;
}

.bots-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
  gap: 1rem;
  overflow-x: hidden;
}

.chat-bot-card {
  min-width: 0;
}
</style>
