<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import ChatBotCard from '@/components/ChatBotCard.vue';
import SessionList from '@/components/SessionList.vue';
import BotDialog from '@/components/BotDialog.vue';
import { useBotStore } from '@/stores/botStore';
import type { ChatBot, BotSession, Message } from '@/types';

const botStore = useBotStore();

const selectedBot = ref<ChatBot | null>(null);
const selectedSession = ref<BotSession | null>(null);
const showCreateBotModal = ref(false);
const showCreateSessionModal = ref(false);
const activeTab = ref<'bots' | 'sessions' | 'messages'>('bots');

const newBotForm = ref({
    name: '',
    platform: 'max' as 'whatsapp' | 'telegram' | 'vk' | 'max',
    client_phone: '',
    object_id: '',
});

const loading = computed(() => botStore.loading);
const error = computed(() => botStore.error);

// Load bots on mount
onMounted(async () => {
    await botStore.fetchAllChatBots();
});

// Select bot and load its sessions
const selectBot = async (bot: ChatBot) => {
    selectedBot.value = bot;
    selectedSession.value = null;
    botStore.clearCurrentData();
    await botStore.fetchBotSessions(bot.id);
    activeTab.value = 'sessions';
};

// Select session and load its messages
const selectSession = async (session: BotSession) => {
    selectedSession.value = session;
    await botStore.fetchSessionMessages(session.id);
    activeTab.value = 'messages';
};

// Handle session actions
const pauseSession = async (session: BotSession) => {
    await botStore.pauseSession(session.id);
    await botStore.fetchBotSessions(selectedBot.value!.id);
};

const resumeSession = async (session: BotSession) => {
    await botStore.resumeSession(session.id);
    await botStore.fetchBotSessions(selectedBot.value!.id);
};

// Handle delete bot
const deleteBot = async (bot: ChatBot) => {
    if (confirm(`Вы уверены, что хотите удалить бота "${bot.name}"?`)) {
        await botStore.deleteChatBot(bot.id);
        if (selectedBot.value?.id === bot.id) {
            selectedBot.value = null;
            selectedSession.value = null;
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

// Create session
const createSession = async () => {
    if (!selectedBot.value) return;
    
    try {
        const session = await botStore.createSession({ chat_bot_id: selectedBot.value.id });
        await botStore.fetchBotSessions(selectedBot.value.id);
        selectSession(session);
    } catch (err) {
        console.error('Failed to create session:', err);
    }
};

// Send message
const sendMessage = async (content: string) => {
    if (!selectedSession.value) return;
    
    try {
        await botStore.sendMessage(selectedSession.value.id, content);
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
        <button 
          class="btn btn--primary"
          @click="showCreateBotModal = true"
        >
          + Создать бота
        </button>
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
            />
          </div>
        </div>

        <!-- Selected bot details -->
        <div v-if="selectedBot" class="details-section">
          <!-- Tabs -->
          <div class="tabs">
            <button
              class="tab"
              :class="{ 'tab--active': activeTab === 'sessions' }"
              @click="activeTab = 'sessions'"
            >
              Сессии
            </button>
            <button
              v-if="selectedSession"
              class="tab"
              :class="{ 'tab--active': activeTab === 'messages' }"
              @click="activeTab = 'messages'"
            >
              Сообщения
            </button>
          </div>

          <!-- Sessions tab -->
          <div v-if="activeTab === 'sessions'" class="tab-content">
            <div class="tab-content__header">
              <h3>Сессии бота</h3>
              <button 
                class="btn btn--primary btn--sm"
                @click="createSession"
              >
                + Создать сессию
              </button>
            </div>
            <SessionList
              :sessions="botStore.sessions"
              :selectedSessionId="selectedSession?.id"
              @select="selectSession"
              @pause="pauseSession"
              @resume="resumeSession"
            />
          </div>

          <!-- Messages tab -->
          <div v-if="activeTab === 'messages' && selectedSession" class="tab-content">
            <BotDialog
              :messages="botStore.messages"
              :loading="loading"
              @send="sendMessage"
            />
          </div>
        </div>

        <!-- Empty state -->
        <div v-else class="empty-details">
          <p>Выберите бота для просмотра деталей</p>
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
                <option value="vk">VK</option>
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

