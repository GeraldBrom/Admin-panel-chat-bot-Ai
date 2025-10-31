<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
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
    await botStore.fetchAllChatBots();
});

// Select bot and load its messages
const selectBot = async (bot: ChatBot) => {
    selectedBot.value = bot;
    // Загружаем последние сообщения для выбранного бота
    // await botStore.fetchSessionMessages(bot.id);
};

// Handle delete bot
const deleteBot = async (bot: ChatBot) => {
    if (confirm(`Вы уверены, что хотите остановить бота "${bot.chat_id}"?`)) {
        await botStore.deleteChatBot(bot.chat_id);
        if (selectedBot.value?.chat_id === bot.chat_id) {
            selectedBot.value = null;
        }
    }
};

// Create bot
const createBot = async () => {
    if (!newBotForm.value.chat_id || !newBotForm.value.object_id) {
        alert('Заполните все обязательные поля');
        return;
    }
    
    try {
        await botStore.createChatBot(newBotForm.value);
        showCreateBotModal.value = false;
        newBotForm.value = { chat_id: '', object_id: 0, bot_config_id: undefined };
    } catch (err) {
        console.error('Failed to create bot:', err);
    }
};

// Toggle bot status
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
    } catch (err) {
        console.error('Failed to toggle bot:', err);
    }
};

// Stop all bots
const stopAllBots = async () => {
    if (confirm('Остановить всех ботов?')) {
        try {
            await botStore.stopAllBots();
        } catch (err) {
            console.error('Failed to stop all bots:', err);
        }
    }
};

// Send message
const sendMessage = async (content: string) => {
    if (!selectedBot.value) return;
    
    try {
        // Отправляем сообщение напрямую в чат с ботом
        // await botStore.sendMessage(selectedBot.value.id, content);
        console.log('Sending message to bot:', selectedBot.value.id, content);
    } catch (err) {
        console.error('Failed to send message:', err);
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
              <label class="form-label">WhatsApp Chat ID *</label>
              <input
                v-model="newBotForm.chat_id"
                type="text"
                class="form-input"
                placeholder="79001234567@c.us"
              />
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
