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

// Фильтры и поиск
const searchQuery = ref('');
const statusFilter = ref<'all' | 'running' | 'stopped' | 'paused' | 'completed'>('all');
const sortBy = ref<'status' | 'messages' | 'activity'>('activity');
const sortOrder = ref<'asc' | 'desc'>('desc');
const viewMode = ref<'grid' | 'list'>('grid');
const groupByStatus = ref(false);

// Пагинация для производительности
const itemsPerPage = ref(50);
const currentPage = ref(1);

const loading = computed(() => botStore.loading);
const error = computed(() => botStore.error);
const validBots = computed(() => botStore.chatBots.filter(bot => bot !== null && bot !== undefined));

// Статистика
const stats = computed(() => {
    const bots = validBots.value;
    return {
        total: bots.length,
        running: bots.filter(b => b.status === 'running').length,
        stopped: bots.filter(b => b.status === 'stopped').length,
        paused: bots.filter(b => b.status === 'paused').length,
        completed: bots.filter(b => b.status === 'completed').length,
        totalMessages: bots.reduce((sum, b) => sum + (b.messages?.length || 0), 0),
    };
});

// Фильтрованные боты
const filteredBots = computed(() => {
    let bots = validBots.value;
    
    // Поиск
    if (searchQuery.value) {
        const query = searchQuery.value.toLowerCase();
        bots = bots.filter(bot => 
            bot.chat_id.toLowerCase().includes(query) ||
            bot.object_id.toString().includes(query)
        );
    }
    
    // Фильтр по статусу
    if (statusFilter.value !== 'all') {
        bots = bots.filter(bot => bot.status === statusFilter.value);
    }
    
    // Сортировка
    bots = [...bots].sort((a, b) => {
        let compareValue = 0;
        
        switch (sortBy.value) {
            case 'status':
                compareValue = a.status.localeCompare(b.status);
                break;
            case 'messages':
                compareValue = (a.messages?.length || 0) - (b.messages?.length || 0);
                break;
            case 'activity':
                const aLastMsg = a.messages?.[a.messages.length - 1]?.created_at || a.created_at;
                const bLastMsg = b.messages?.[b.messages.length - 1]?.created_at || b.created_at;
                compareValue = new Date(aLastMsg).getTime() - new Date(bLastMsg).getTime();
                break;
        }
        
        return sortOrder.value === 'asc' ? compareValue : -compareValue;
    });
    
    return bots;
});

// Пагинированные боты
const paginatedBots = computed(() => {
    const start = (currentPage.value - 1) * itemsPerPage.value;
    const end = start + itemsPerPage.value;
    return filteredBots.value.slice(start, end);
});

const totalPages = computed(() => {
    return Math.ceil(filteredBots.value.length / itemsPerPage.value);
});

// Группированные боты
const groupedBots = computed(() => {
    if (!groupByStatus.value) return null;
    
    const groups: Record<string, ChatBot[]> = {
        running: [],
        stopped: [],
        paused: [],
        completed: [],
    };
    
    paginatedBots.value.forEach(bot => {
        if (groups[bot.status]) {
            groups[bot.status].push(bot);
        }
    });
    
    return groups;
});

// Сброс страницы при изменении фильтров
watch([searchQuery, statusFilter, sortBy, sortOrder], () => {
    currentPage.value = 1;
});

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
      </div>

      <div v-if="error" class="alert alert--danger">
        {{ error }}
      </div>

      <!-- Панель инструментов -->
      <div class="toolbar">
        <div class="toolbar__left">
          <!-- Компактная статистика -->
          <div class="stats-compact">
            <div class="stat-item">
              <span class="stat-item__icon">📊</span>
              <span class="stat-item__value">{{ stats.total }}</span>
            </div>
            <div class="stat-item stat-item--success">
              <span class="stat-item__icon">▶️</span>
              <span class="stat-item__value">{{ stats.running }}</span>
            </div>
            <div class="stat-item stat-item--danger">
              <span class="stat-item__icon">⏸️</span>
              <span class="stat-item__value">{{ stats.stopped }}</span>
            </div>
            <div class="stat-item stat-item--info">
              <span class="stat-item__icon">💬</span>
              <span class="stat-item__value">{{ stats.totalMessages }}</span>
            </div>
          </div>
          
          <div class="toolbar__search">
            <input
              v-model="searchQuery"
              type="text"
              class="search-input"
              placeholder="🔍 Поиск по Chat ID или Object ID..."
            />
          </div>
          
          <div class="toolbar__filters">
            <div class="filter-group">
              <label class="filter-label">Статус:</label>
              <button 
                class="filter-btn" 
                :class="{ 'filter-btn--active': statusFilter === 'all' }"
                @click="statusFilter = 'all'"
              >
                Все <span class="badge">{{ stats.total }}</span>
              </button>
              <button 
                class="filter-btn" 
                :class="{ 'filter-btn--active': statusFilter === 'running' }"
                @click="statusFilter = 'running'"
              >
                Активные <span class="badge badge--success">{{ stats.running }}</span>
              </button>
              <button 
                class="filter-btn" 
                :class="{ 'filter-btn--active': statusFilter === 'stopped' }"
                @click="statusFilter = 'stopped'"
              >
                Остановлены <span class="badge badge--danger">{{ stats.stopped }}</span>
              </button>
            </div>
            
            <div class="filter-group">
              <label class="filter-label">Сортировка:</label>
              <select v-model="sortBy" class="sort-select">
                <option value="activity">По активности</option>
                <option value="messages">По количеству сообщений</option>
                <option value="status">По статусу</option>
              </select>
            </div>
            
            <div class="filter-group">
              <button 
                class="btn btn--ghost btn--sm"
                :class="{ 'btn--active': viewMode === 'grid' }"
                @click="viewMode = 'grid'"
                title="Сетка"
              >
                ▦
              </button>
              <button 
                class="btn btn--ghost btn--sm"
                :class="{ 'btn--active': viewMode === 'list' }"
                @click="viewMode = 'list'"
                title="Список"
              >
                ☰
              </button>
              <button 
                class="btn btn--ghost btn--sm"
                :class="{ 'btn--active': groupByStatus }"
                @click="groupByStatus = !groupByStatus"
                title="Группировать по статусу"
              >
                📁
              </button>
            </div>
          </div>
        </div>
        
        <div class="toolbar__actions">
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

      <div class="chat-bots-content">
        <div class="bots-section">
          <!-- Список ботов -->
          <div v-if="!groupByStatus" class="bots-grid" :class="`bots-grid--${viewMode}`">
            <ChatBotCard
              v-for="bot in paginatedBots"
              :key="bot.chat_id"
              :bot="bot"
              :selected="selectedBot?.chat_id === bot.chat_id"
              :view-mode="viewMode"
              @select="selectBot"
              @edit="() => {}"
              @delete="deleteBot"
              @toggle="toggleBot"
            />
          </div>
          
          <!-- Сгруппированный список -->
          <div v-else class="bots-grouped">
            <div 
              v-for="(bots, status) in groupedBots" 
              :key="status"
              v-show="bots.length > 0"
              class="bot-group"
            >
              <div class="bot-group__header">
                <h3 class="bot-group__title">
                  <span class="status-indicator" :class="`status-indicator--${status}`"></span>
                  {{ status === 'running' ? 'Активные' : status === 'stopped' ? 'Остановленные' : status === 'paused' ? 'На паузе' : 'Завершенные' }}
                  <span class="bot-group__count">{{ bots.length }}</span>
                </h3>
              </div>
              <div class="bots-grid" :class="`bots-grid--${viewMode}`">
                <ChatBotCard
                  v-for="bot in bots"
                  :key="bot.chat_id"
                  :bot="bot"
                  :selected="selectedBot?.chat_id === bot.chat_id"
                  :view-mode="viewMode"
                  @select="selectBot"
                  @edit="() => {}"
                  @delete="deleteBot"
                  @toggle="toggleBot"
                />
              </div>
            </div>
          </div>
          
          <div v-if="filteredBots.length === 0" class="empty-state">
            <p>{{ searchQuery ? 'Ничего не найдено' : 'Нет ботов' }}</p>
          </div>
          
          <!-- Пагинация -->
          <div v-if="totalPages > 1" class="pagination">
            <button 
              class="pagination__btn"
              :disabled="currentPage === 1"
              @click="currentPage--"
            >
              ← Назад
            </button>
            
            <div class="pagination__info">
              Страница {{ currentPage }} из {{ totalPages }}
              <span class="pagination__count">({{ filteredBots.length }} ботов)</span>
            </div>
            
            <button 
              class="pagination__btn"
              :disabled="currentPage === totalPages"
              @click="currentPage++"
            >
              Вперёд →
            </button>
          </div>
        </div>

        <div v-if="selectedBot" class="chat-section">
          <div class="chat-header">
            <div class="chat-header__info">
              <div class="chat-header__title">
                <h2>{{ selectedBot.chat_id }}</h2>
                <div class="chat-header__meta">
                  <span class="chat-platform">{{ selectedBot.platform }}</span>
                  <span class="chat-meta">
                    <span class="chat-meta__label">ID объекта</span>
                    <span class="chat-meta__value">{{ selectedBot.object_id }}</span>
                  </span>
                </div>
              </div>
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
