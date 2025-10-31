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
    name: '',
    platform: 'max' as 'whatsapp' | 'telegram' | 'max',
    client_phone: '',
    object_id: '',
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
    if (confirm(`Вы уверены, что хотите удалить бота "${bot.name}"?`)) {
        await botStore.deleteChatBot(bot.id);
        if (selectedBot.value?.id === bot.id) {
            selectedBot.value = null;
        }
    }
};

// Create bot
const createBot = async () => {
    if (!newBotForm.value.name || !newBotForm.value.object_id) {
        alert('Заполните все обязательные поля');
        return;
    }
    
    try {
        await botStore.createChatBot(newBotForm.value);
        showCreateBotModal.value = false;
        newBotForm.value = { name: '', platform: 'max', client_phone: '', object_id: '' };
    } catch (err) {
        console.error('Failed to create bot:', err);
    }
};

// Toggle bot status
const toggleBot = async (bot: ChatBot) => {
    try {
        const newStatus = bot.status === 'online' ? 'offline' : 'online';
        await botStore.updateChatBot(bot.id, { status: newStatus });
    } catch (err) {
        console.error('Failed to toggle bot:', err);
    }
};

// Stop all bots
const stopAllBots = async () => {
    if (confirm('Остановить всех ботов?')) {
        try {
            for (const bot of botStore.chatBots) {
                if (bot.status === 'online') {
                    await botStore.updateChatBot(bot.id, { status: 'offline' });
                }
            }
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
              :selected="selectedBot?.id === bot.id"
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
              <h2>{{ selectedBot.name }}</h2>
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
              <label class="form-label">Название *</label>
              <input
                v-model="newBotForm.name"
                type="text"
                class="form-input"
                placeholder="Название бота"
              />
            </div>
            <div class="form-group">
              <label class="form-label">Платформа *</label>
              <select v-model="newBotForm.platform" class="form-input">
                <option value="max">MAX</option>
                <option value="whatsapp">WhatsApp</option>
                <option value="telegram">Telegram</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Телефон клиента</label>
              <input
                v-model="newBotForm.client_phone"
                type="text"
                class="form-input"
                placeholder="+7... (опционально)"
              />
            </div>
            <div class="form-group">
              <label class="form-label">ID объекта *</label>
              <input
                v-model="newBotForm.object_id"
                type="text"
                class="form-input"
                placeholder="ID объекта"
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
