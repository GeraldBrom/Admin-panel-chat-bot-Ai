<script setup lang="ts">
import { ref, onMounted, computed, watch, onBeforeUnmount } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import MessageList from '@/components/MessageList.vue';
import { useScenarioBotStore } from '@/stores/scenarioBotStore';
import type { ScenarioBotSession, ScenarioBot } from '@/types';

const scenarioBotStore = useScenarioBotStore();

const selectedSession = ref<ScenarioBotSession | null>(null);
const showCreateSessionModal = ref(false);
const availableBots = ref<ScenarioBot[]>([]);

const newSessionForm = ref({
    scenario_bot_id: undefined as number | undefined,
    chat_id: '',
    object_id: 0,
    platform: 'whatsapp' as 'whatsapp' | 'telegram' | 'max',
});

const loading = computed(() => scenarioBotStore.loading);
const error = computed(() => scenarioBotStore.error);
const sessions = computed(() => scenarioBotStore.sessions);

onMounted(async () => {
    // Загружаем список всех сценарных ботов
    await scenarioBotStore.fetchAllScenarioBots();
    availableBots.value = scenarioBotStore.scenarioBots; // Все боты, не только активные
    
    // Если есть боты, подгружаем сессии первого
    if (availableBots.value.length > 0) {
        newSessionForm.value.scenario_bot_id = availableBots.value[0].id;
        await loadBotSessions(availableBots.value[0].id);
    }
});

const loadBotSessions = async (botId: number) => {
    try {
        await scenarioBotStore.fetchBotSessions(botId);
    } catch (err) {
        console.error('Failed to load sessions:', err);
    }
};

const selectSession = async (session: ScenarioBotSession) => {
    selectedSession.value = session;
    
    // Загружаем полную информацию о сессии с сообщениями
    try {
        const fullSession = await scenarioBotStore.getSession(session.chat_id);
        selectedSession.value = fullSession;
    } catch (err) {
        console.error('Failed to load session details:', err);
    }
};

// Поллинг для обновления выбранной сессии
const pollTimer = ref<number | null>(null);

watch(
    () => selectedSession.value?.id,
    async (sessionId) => {
        // Очищаем предыдущий таймер
        if (pollTimer.value) {
            clearInterval(pollTimer.value);
            pollTimer.value = null;
        }
        
        if (!sessionId || !selectedSession.value) return;

        // Устанавливаем новый таймер для автообновления
        pollTimer.value = window.setInterval(async () => {
            try {
                const chatId = selectedSession.value?.chat_id;
                if (!chatId) return;
                
                const fullSession = await scenarioBotStore.getSession(chatId);
                selectedSession.value = fullSession;
            } catch (e) {
                console.error('[ScenarioBotSessions] Failed to refresh session:', e);
            }
        }, 5000); // Обновляем каждые 5 секунд
    }
);

onBeforeUnmount(() => {
    if (pollTimer.value) {
        clearInterval(pollTimer.value);
        pollTimer.value = null;
    }
});

// Преобразуем сообщения для MessageList
const displayMessages = computed(() => {
    if (!selectedSession.value?.messages) return [];
    
    // Преобразуем ScenarioBotMessage в Message
    return selectedSession.value.messages.map(msg => ({
        id: msg.id,
        dialog_id: selectedSession.value!.chat_id,
        role: msg.role,
        content: msg.content,
        tokens_in: undefined,
        tokens_out: undefined,
        meta: msg.meta || {},
        created_at: msg.created_at,
        updated_at: msg.updated_at,
    }));
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
    if (!newSessionForm.value.chat_id) {
        alert('Заполните номер WhatsApp');
        return;
    }
    
    if (availableBots.value.length === 0) {
        alert('Нет сценарных ботов. Создайте хотя бы одного бота.');
        return;
    }
    
    try {
        // Автоматически берем первого бота из списка
        const botId = availableBots.value[0].id;
        
        const session = await scenarioBotStore.startSession(
            formatChatId(newSessionForm.value.chat_id),
            botId,
            newSessionForm.value.object_id,
            'whatsapp' // Всегда WhatsApp
        );
        
        showCreateSessionModal.value = false;
        newSessionForm.value = {
            scenario_bot_id: undefined,
            chat_id: '',
            object_id: 0,
            platform: 'whatsapp',
        };
        
        // Перезагружаем сессии
        await loadBotSessions(botId);
        
        selectedSession.value = session;
    } catch (err) {
        console.error('Failed to create session:', err);
        alert('Ошибка создания сессии: ' + (err as any)?.response?.data?.message || 'Неизвестная ошибка');
    }
};

const stopSession = async (session: ScenarioBotSession) => {
    if (confirm(`Остановить сессию для "${session.chat_id}"?`)) {
        try {
            await scenarioBotStore.stopSession(session.chat_id);
            
            // Перезагружаем сессии
            if (session.scenario_bot_id) {
                await loadBotSessions(session.scenario_bot_id);
            }
            
            if (selectedSession.value?.chat_id === session.chat_id) {
                selectedSession.value = null;
            }
        } catch (err) {
            console.error('Failed to stop session:', err);
        }
    }
};

const resetSession = async (session: ScenarioBotSession) => {
    if (confirm(`Сбросить сессию для "${session.chat_id}"?\n\nБот начнет сценарий сначала.`)) {
        try {
            await scenarioBotStore.resetSession(session.chat_id);
            alert('Сессия сброшена');
        } catch (err) {
            console.error('Failed to reset session:', err);
            alert('Ошибка сброса сессии');
        }
    }
};

const restartSession = async (session: ScenarioBotSession) => {
    if (confirm(`Перезапустить сессию для "${session.chat_id}"?`)) {
        try {
            // Перезапускаем остановленную сессию
            await scenarioBotStore.startSession(
                session.chat_id,
                session.scenario_bot_id,
                session.object_id || 0,
                session.platform
            );
            
            // Перезагружаем сессии
            if (session.scenario_bot_id) {
                await loadBotSessions(session.scenario_bot_id);
            }
            
            alert('Сессия перезапущена');
        } catch (err) {
            console.error('Failed to restart session:', err);
            alert('Ошибка перезапуска сессии');
        }
    }
};

const getStatusBadge = (status: string) => {
    return {
        'running': { text: 'Активна', class: 'badge--success' },
        'paused': { text: 'На паузе', class: 'badge--warning' },
        'stopped': { text: 'Остановлена', class: 'badge--danger' },
        'completed': { text: 'Завершена', class: 'badge--info' },
    }[status] || { text: status, class: '' };
};
</script>

<template>
  <MainLayout>
    <div class="scenario-sessions-page">
      <div class="page-header">
        <div>
          <h1>💬 Сессии сценарных ботов</h1>
          <p>Управление активными диалогами</p>
        </div>
        <div class="page-actions">
          <button class="btn btn--primary" @click="showCreateSessionModal = true">
            ➕ Создать сессию
          </button>
        </div>
      </div>

      <div v-if="error" class="alert alert--danger">
        {{ error }}
      </div>

      <div class="sessions-content">
        <!-- Список сессий слева -->
        <div class="sessions-section">
          <h2>Сессии ({{ sessions.length }})</h2>
          
          <div v-if="sessions.length === 0" class="empty-state">
            <p>Нет активных сессий</p>
            <button class="btn btn--primary btn--sm" @click="showCreateSessionModal = true">
              Создать сессию
            </button>
          </div>
          
          <div v-else class="sessions-list">
            <div
              v-for="session in sessions"
              :key="session.id"
              class="session-card"
              :class="{ 'session-card--selected': selectedSession?.id === session.id }"
              @click="selectSession(session)"
            >
              <div class="session-card__header">
                <strong>{{ session.chat_id }}</strong>
                <span class="badge" :class="getStatusBadge(session.status).class">
                  {{ getStatusBadge(session.status).text }}
                </span>
              </div>
              <div class="session-card__body">
                <div class="session-info">
                  <span class="label">Объект:</span>
                  <span>{{ session.object_id || '-' }}</span>
                </div>
                <div class="session-info">
                  <span class="label">Платформа:</span>
                  <span>{{ session.platform }}</span>
                </div>
              </div>
              <div class="session-card__footer">
                <button
                  v-if="session.status === 'running'"
                  class="btn btn--sm btn--ghost"
                  @click.stop="resetSession(session)"
                >
                  🔄 Сбросить
                </button>
                <button
                  v-if="session.status === 'running'"
                  class="btn btn--sm btn--danger"
                  @click.stop="stopSession(session)"
                >
                  ⏹️ Остановить
                </button>
                <button
                  v-if="session.status === 'stopped' || session.status === 'completed'"
                  class="btn btn--sm btn--success"
                  @click.stop="restartSession(session)"
                >
                  ▶️ Запустить
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Чат справа -->
        <div v-if="selectedSession" class="chat-section">
          <div class="chat-header">
            <div class="chat-header__info">
              <h2>{{ selectedSession.chat_id }}</h2>
              <span class="badge" :class="getStatusBadge(selectedSession.status).class">
                {{ getStatusBadge(selectedSession.status).text }}
              </span>
            </div>
          </div>

          <div class="chat-messages">
            <MessageList :messages="displayMessages" />
          </div>
        </div>

        <div v-else class="empty-chat">
          <div class="empty-icon">💬</div>
          <p>Выберите сессию для просмотра деталей</p>
        </div>
      </div>

      <!-- Модальное окно создания сессии -->
      <div v-if="showCreateSessionModal" class="modal-overlay" @click.self="showCreateSessionModal = false">
        <div class="modal" @click.stop>
          <div class="modal__header">
            <h3>Создать сессию</h3>
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
              <label class="form-label">ID объекта</label>
              <input
                v-model.number="newSessionForm.object_id"
                type="number"
                class="form-input"
                placeholder="508437"
              />
              <small class="form-help">Необязательное поле</small>
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

<style scoped lang="scss">
.scenario-sessions-page {
  padding: 2rem;
  max-width: 1400px;
  margin: 0 auto;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid #e0e0e0;

  h1 {
    font-size: 1.875rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #1a1a1a;
  }

  p {
    color: #666;
    font-size: 0.95rem;
  }
}

.page-actions {
  display: flex;
  gap: 1rem;
}

.alert {
  padding: 1rem;
  border-radius: 6px;
  margin-bottom: 1.5rem;

  &--danger {
    background: #fee;
    color: #c33;
    border: 1px solid #fcc;
  }
}

.sessions-content {
  display: grid;
  grid-template-columns: 400px 1fr;
  gap: 2rem;
  height: calc(100vh - 200px);
}

.sessions-section {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow-y: auto;

  h2 {
    font-size: 1.25rem;
    font-weight: 600;
    margin-bottom: 1.5rem;
    color: #333;
  }
}

.empty-state {
  text-align: center;
  padding: 3rem 1rem;
  color: #666;

  p {
    margin-bottom: 1rem;
  }
}

.sessions-list {
  display: flex;
  flex-direction: column;
  gap: 1rem;
}

.session-card {
  padding: 1rem;
  border: 1px solid #e0e0e0;
  border-radius: 8px;
  cursor: pointer;
  transition: all 0.2s;

  &:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  }

  &--selected {
    border-color: #007bff;
    background: #f0f8ff;
  }

  &__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 0.75rem;

    strong {
      font-size: 0.95rem;
      color: #333;
    }
  }

  &__body {
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
    margin-bottom: 0.75rem;
  }

  &__footer {
    display: flex;
    gap: 0.5rem;
    padding-top: 0.75rem;
    border-top: 1px solid #f0f0f0;
  }
}

.session-info {
  display: flex;
  font-size: 0.875rem;

  .label {
    color: #999;
    margin-right: 0.5rem;
  }
}

.badge {
  padding: 0.25rem 0.5rem;
  border-radius: 4px;
  font-size: 0.75rem;
  font-weight: 500;

  &--success {
    background: #d4edda;
    color: #155724;
  }

  &--warning {
    background: #fff3cd;
    color: #856404;
  }

  &--danger {
    background: #f8d7da;
    color: #721c24;
  }

  &--info {
    background: #d1ecf1;
    color: #0c5460;
  }
}

.chat-section {
  background: white;
  border-radius: 8px;
  padding: 1.5rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow-y: auto;
}

.chat-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding-bottom: 1rem;
  border-bottom: 1px solid #e0e0e0;
  margin-bottom: 1.5rem;

  &__info {
    display: flex;
    align-items: center;
    gap: 1rem;

    h2 {
      margin: 0;
      font-size: 1.25rem;
      font-weight: 600;
      color: #333;
    }
  }
}

.chat-messages {
  background: #f8f9fa;
  border-radius: 6px;
  padding: 1rem;
  max-height: calc(100vh - 300px);
  overflow-y: auto;
}

.empty-chat {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  background: white;
  border-radius: 8px;
  padding: 4rem 2rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);

  .empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
    opacity: 0.3;
  }

  p {
    color: #999;
    font-size: 1.1rem;
  }
}

.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  right: 0;
  bottom: 0;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 1000;
}

.modal {
  background: white;
  border-radius: 8px;
  width: 90%;
  max-width: 600px;
  max-height: 90vh;
  overflow: auto;

  &__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.5rem;
    border-bottom: 1px solid #e0e0e0;

    h3 {
      margin: 0;
      font-size: 1.25rem;
      font-weight: 600;
      color: #333;
    }
  }

  &__body {
    padding: 1.5rem;
  }

  &__footer {
    display: flex;
    justify-content: flex-end;
    gap: 1rem;
    padding: 1.5rem;
    border-top: 1px solid #e0e0e0;
  }
}

.form-group {
  margin-bottom: 1.5rem;

  &:last-child {
    margin-bottom: 0;
  }
}

.form-label {
  display: block;
  font-weight: 600;
  margin-bottom: 0.5rem;
  color: #333;
  font-size: 0.9rem;
}

.form-input {
  width: 100%;
  padding: 0.75rem;
  border: 1px solid #ced4da;
  border-radius: 6px;
  font-size: 0.95rem;
  transition: border-color 0.15s;

  &:focus {
    outline: none;
    border-color: #007bff;
    box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
  }
}

.form-help {
  display: block;
  margin-top: 0.5rem;
  font-size: 0.875rem;
  color: #6c757d;
}

.btn {
  padding: 0.625rem 1.25rem;
  border: none;
  border-radius: 6px;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  &--primary {
    background: #007bff;
    color: white;

    &:hover:not(:disabled) {
      background: #0056b3;
    }
  }

  &--danger {
    background: #dc3545;
    color: white;

    &:hover:not(:disabled) {
      background: #c82333;
    }
  }

  &--success {
    background: #28a745;
    color: white;

    &:hover:not(:disabled) {
      background: #218838;
    }
  }

  &--ghost {
    background: transparent;
    color: #6c757d;
    border: 1px solid #ced4da;

    &:hover:not(:disabled) {
      background: #f8f9fa;
      color: #495057;
    }
  }

  &--sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.875rem;
  }
}
</style>

