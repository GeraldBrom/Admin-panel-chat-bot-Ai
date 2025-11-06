<script setup lang="ts">
import { ref, onMounted, computed, watch, onBeforeUnmount } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import ChatBotCard from '@/components/ChatBotCard.vue';
import BotDialog from '@/components/BotDialog.vue';
import api from '@/services/api';

interface ChatKitSession {
  id: number;
  chat_id: string;
  object_id: number;
  platform: string;
  agent_id: string;
  status: string;
  started_at: string;
  stopped_at?: string;
  messages?: ChatKitMessage[];
}

interface ChatKitMessage {
  id: number;
  role: string;
  content: string;
  created_at: string;
}

const sessions = ref<ChatKitSession[]>([]);
const selectedSession = ref<ChatKitSession | null>(null);
const showCreateSessionModal = ref(false);
const loading = ref(false);
const error = ref<string | null>(null);

const newSessionForm = ref({
  chat_id: '',
  object_id: 0,
  platform: 'whatsapp',
});

const validSessions = computed(() => sessions.value.filter(s => s !== null && s !== undefined));

onMounted(async () => {
  await fetchSessions();
});

const fetchSessions = async () => {
  try {
    loading.value = true;
    error.value = null;
    const response = await api.get('/chatkit/sessions');
    sessions.value = response.data.data || [];
  } catch (err: any) {
    console.error('[ChatKitSessions] Failed to fetch sessions:', err);
    error.value = err.response?.data?.message || 'Ошибка загрузки сессий';
  } finally {
    loading.value = false;
  }
};

const selectSession = async (session: ChatKitSession) => {
  try {
    selectedSession.value = session;
    if (!session.messages) {
      const response = await api.get(`/chatkit/sessions/${encodeURIComponent(session.chat_id)}`);
      selectedSession.value = response.data.data;
    }
  } catch (err) {
    console.error('[ChatKitSessions] Failed to select session:', err);
  }
};

const pollTimer = ref<number | null>(null);

watch(
  () => selectedSession.value?.chat_id,
  async (chatId) => {
    if (pollTimer.value) {
      clearInterval(pollTimer.value);
      pollTimer.value = null;
    }
    if (!chatId) return;

    try {
      const response = await api.get(`/chatkit/sessions/${chatId}`);
      selectedSession.value = response.data.data;
    } catch (e) {
      console.error('[ChatKitSessions] Initial refresh failed:', e);
    }

    pollTimer.value = window.setInterval(async () => {
      try {
        const id = selectedSession.value?.chat_id;
        if (!id) return;
        const response = await api.get(`/chatkit/sessions/${id}`);
        selectedSession.value = response.data.data;
      } catch (e) {
        // Игнорируем ошибки при polling
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

const formatChatId = (phone: string): string => {
  if (!phone) return '';
  const digits = phone.replace(/\D/g, '');
  if (!phone.includes('@')) {
    return `${digits}@c.us`;
  }
  return phone;
};

const createSession = async () => {
  if (!newSessionForm.value.chat_id || !newSessionForm.value.object_id) {
    alert('Заполните все обязательные поля');
    return;
  }
  
  try {
    loading.value = true;
    error.value = null;
    
    const formattedData = {
      ...newSessionForm.value,
      chat_id: formatChatId(newSessionForm.value.chat_id),
    };
    
    console.log('[ChatKitSessions] Creating session:', formattedData);
    
    const response = await api.post('/chatkit/sessions/start', formattedData);
    
    console.log('[ChatKitSessions] Session created:', response.data);
    
    showCreateSessionModal.value = false;
    newSessionForm.value = { chat_id: '', object_id: 0, platform: 'whatsapp' };
    
    // Даем немного времени серверу для записи в БД
    await new Promise(resolve => setTimeout(resolve, 500));
    
    await fetchSessions();
    
    // Показываем успешное сообщение
    alert('Сессия ChatKit успешно создана!');
  } catch (err: any) {
    console.error('[ChatKitSessions] Failed to create session:', err);
    const errorMessage = err?.response?.data?.error || err?.response?.data?.message || 'Неизвестная ошибка';
    error.value = 'Ошибка создания сессии: ' + errorMessage;
    alert('Ошибка создания сессии: ' + errorMessage);
  } finally {
    loading.value = false;
  }
};

const toggleSession = async (session: ChatKitSession) => {
  try {
    loading.value = true;
    
    if (session.status === 'running') {
      console.log('[ChatKitSessions] Stopping session:', session.chat_id);
      await api.delete(`/chatkit/sessions/${encodeURIComponent(session.chat_id)}`);
    } else {
      console.log('[ChatKitSessions] Starting session:', session.chat_id);
      await api.post('/chatkit/sessions/start', {
        chat_id: session.chat_id,
        object_id: session.object_id,
        platform: session.platform,
      });
    }
    
    // Даем серверу время на обновление
    await new Promise(resolve => setTimeout(resolve, 300));
    
    // Перезагружаем все сессии
    await fetchSessions();
    
    // Обновляем выбранную сессию с сервера
    if (selectedSession.value?.chat_id === session.chat_id) {
      try {
        const response = await api.get(`/chatkit/sessions/${encodeURIComponent(session.chat_id)}`);
        selectedSession.value = response.data.data;
        console.log('[ChatKitSessions] Session updated:', response.data.data);
      } catch (err) {
        console.error('[ChatKitSessions] Failed to refresh selected session:', err);
        // Если не удалось загрузить, берем из списка
        const updatedSession = sessions.value.find(s => s.chat_id === session.chat_id);
        if (updatedSession) {
          selectedSession.value = updatedSession;
        }
      }
    }
  } catch (err: any) {
    console.error('[ChatKitSessions] Failed to toggle session:', err);
    const errorMessage = err?.response?.data?.error || err?.response?.data?.message || 'Неизвестная ошибка';
    alert('Ошибка изменения статуса сессии: ' + errorMessage);
  } finally {
    loading.value = false;
  }
};

const stopAllSessions = async () => {
  if (confirm('Остановить все сессии ChatKit?')) {
    try {
      loading.value = true;
      await api.post('/chatkit/sessions/stop-all');
      await fetchSessions();
      
      if (selectedSession.value) {
        const updatedSession = sessions.value.find(s => s.chat_id === selectedSession.value?.chat_id);
        if (updatedSession) {
          selectedSession.value = updatedSession;
        }
      }
    } catch (err) {
      console.error('[ChatKitSessions] Failed to stop all sessions:', err);
    } finally {
      loading.value = false;
    }
  }
};

const clearSession = async (session: ChatKitSession) => {
  if (confirm(`Очистить контекст для "${session.chat_id}"?\n\nВсе сообщения диалога будут удалены, но сессия останется активной.`)) {
    try {
      await api.delete(`/chatkit/sessions/${encodeURIComponent(session.chat_id)}/clear`);
      
      if (selectedSession.value?.chat_id === session.chat_id) {
        selectedSession.value = {
          ...selectedSession.value,
          messages: []
        };
      }
      
      setTimeout(async () => {
        try {
          if (selectedSession.value?.chat_id === session.chat_id) {
            const response = await api.get(`/chatkit/sessions/${encodeURIComponent(session.chat_id)}`);
            selectedSession.value = response.data.data;
          }
        } catch (e) {
          console.error('[ChatKitSessions] Failed to refresh after clear:', e);
        }
      }, 300);
      
      alert('Контекст сессии успешно очищен');
    } catch (err) {
      console.error('[ChatKitSessions] Failed to clear session:', err);
      alert('Ошибка очистки контекста сессии');
    }
  }
};

const deleteSession = async (session: ChatKitSession) => {
  if (confirm(`Вы уверены, что хотите остановить сессию "${session.chat_id}"?`)) {
    try {
      loading.value = true;
      await api.delete(`/chatkit/sessions/${encodeURIComponent(session.chat_id)}`);
      
      // Даем серверу время на обновление
      await new Promise(resolve => setTimeout(resolve, 300));
      
      await fetchSessions();
      
      // Обновляем выбранную сессию
      if (selectedSession.value?.chat_id === session.chat_id) {
        try {
          const response = await api.get(`/chatkit/sessions/${encodeURIComponent(session.chat_id)}`);
          selectedSession.value = response.data.data;
        } catch (err) {
          // Если сессия удалена, очищаем выбор
          const updatedSession = sessions.value.find(s => s.chat_id === session.chat_id);
          selectedSession.value = updatedSession || null;
        }
      }
    } catch (err) {
      console.error('[ChatKitSessions] Failed to delete session:', err);
      alert('Ошибка остановки сессии');
    } finally {
      loading.value = false;
    }
  }
};

// Преобразуем сообщения ChatKit в формат для BotDialog
const messagesForDialog = computed(() => {
  if (!selectedSession.value?.messages) return [];
  return selectedSession.value.messages.map(msg => ({
    id: msg.id,
    dialog_id: null,
    role: msg.role,
    content: msg.content,
    tokens_in: null,
    tokens_out: null,
    meta: {},
    created_at: msg.created_at,
  }));
});

</script>

<template>
  <MainLayout>
    <div class="chat-bots-page">
      <div class="page-header">
        <div>
          <h1>ChatKit Agent сессии</h1>
          <p>Управление сессиями ChatKit через OpenAI Agent Builder</p>
        </div>
        <div class="page-header__actions">
          <button 
            class="btn btn--danger"
            @click="stopAllSessions"
          >
            ⏸️ Остановить все
          </button>
          <button 
            class="btn btn--primary"
            @click="showCreateSessionModal = true"
          >
            + Создать сессию
          </button>
        </div>
      </div>

      <div v-if="error" class="alert alert--danger">
        {{ error }}
      </div>

      <div class="chat-bots-content">
        <div class="bots-section">
          <h2>Список сессий</h2>
          <div class="bots-grid">
            <ChatBotCard
              v-for="session in validSessions"
              :key="session.chat_id"
              :bot="{
                chat_id: session.chat_id,
                object_id: session.object_id,
                platform: session.platform,
                status: session.status,
                messages: session.messages || [],
              }"
              :selected="selectedSession?.chat_id === session.chat_id"
              @select="selectSession(session)"
              @edit="() => {}"
              @delete="deleteSession(session)"
              @toggle="toggleSession(session)"
            />
          </div>
        </div>

        <div v-if="selectedSession" class="chat-section">
          <div class="chat-header">
            <div class="chat-header__info">
              <h2>{{ selectedSession.chat_id }}</h2>
              <span class="chat-platform">{{ selectedSession.platform }}</span>
              <span class="chat-agent">Agent: {{ selectedSession.agent_id }}</span>
            </div>
            <div class="chat-header__actions">
              <button 
                v-if="selectedSession.status === 'running'"
                class="btn btn--warning btn--sm"
                @click="clearSession(selectedSession)"
                title="Очистить контекст сессии"
              >
                🧹 Очистить контекст
              </button>
            </div>
          </div>
          <BotDialog
            :messages="messagesForDialog"
            :loading="loading"
            @send="() => {}"
          />
        </div>

        <div v-else class="empty-chat">
          <p>Выберите сессию для просмотра диалога</p>
        </div>
      </div>

      <div v-if="showCreateSessionModal" class="modal-overlay" @click.self="showCreateSessionModal = false">
        <div class="modal" @click.stop>
          <div class="modal__header">
            <h3>Создать ChatKit сессию</h3>
            <button class="btn btn--ghost btn--sm" @click="showCreateSessionModal = false">✕</button>
          </div>
          <div class="modal__body">
            <div class="form-group">
              <label class="form-label">Номер WhatsApp *</label>
              <input
                v-model="newSessionForm.chat_id"
                type="text"
                class="form-input"
                placeholder="79001234567"
              />
              <small class="form-help">Введите номер без @c.us — он добавится автоматически</small>
            </div>
            <div class="form-group">
              <label class="form-label">ID объекта *</label>
              <input
                v-model.number="newSessionForm.object_id"
                type="number"
                class="form-input"
                placeholder="508437"
              />
            </div>
          </div>
          <div class="modal__footer">
            <button class="btn btn--ghost" @click="showCreateSessionModal = false">Отмена</button>
            <button class="btn btn--primary" @click="createSession" :disabled="loading">
              Создать
            </button>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<style scoped>
.chat-agent {
  display: inline-block;
  margin-left: 1rem;
  padding: 0.25rem 0.5rem;
  background: #e9ecef;
  border-radius: 4px;
  font-size: 0.875rem;
  color: #6c757d;
}
</style>

