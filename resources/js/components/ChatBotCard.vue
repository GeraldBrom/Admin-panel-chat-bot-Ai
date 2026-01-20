<script setup lang="ts">
import { computed } from 'vue';
import type { ChatBot } from '@/types';

interface Props {
    bot: ChatBot;
    selected?: boolean;
    viewMode?: 'grid' | 'list';
}

const props = withDefaults(defineProps<Props>(), {
    selected: false,
    viewMode: 'grid',
});

const emit = defineEmits<{
    (e: 'select', bot: ChatBot): void;
    (e: 'delete', bot: ChatBot): void;
    (e: 'toggle', bot: ChatBot): void;
}>();

// Вычисляемые свойства
const messagesCount = computed(() => props.bot.messages?.length || 0);

const lastMessageTime = computed(() => {
    if (!props.bot.messages || props.bot.messages.length === 0) {
        return 'Нет сообщений';
    }
    const lastMsg = props.bot.messages[props.bot.messages.length - 1];
    return formatRelativeTime(lastMsg.created_at);
});

const statusText = computed(() => {
    const statusMap: Record<string, string> = {
        running: 'Активен',
        stopped: 'Остановлен',
        paused: 'На паузе',
        completed: 'Завершен',
    };
    return statusMap[props.bot.status] || props.bot.status;
});

const isActive = computed(() => props.bot.status === 'running');

// Утилиты
function formatRelativeTime(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);
    
    if (diffMins < 1) return 'только что';
    if (diffMins < 60) return `${diffMins} мин назад`;
    if (diffHours < 24) return `${diffHours} ч назад`;
    if (diffDays < 7) return `${diffDays} дн назад`;
    
    return date.toLocaleDateString('ru-RU');
}

</script>

<template>
    <div 
        class="chat-bot-card" 
        :class="[
            { 'chat-bot-card--selected': selected },
            `chat-bot-card--${bot.status}`,
            `chat-bot-card--${viewMode}`
        ]"
        @click="emit('select', bot)"
    >
        <!-- Индикатор статуса -->
        <div class="status-indicator" :class="`status-indicator--${bot.status}`">
            <span class="status-pulse" v-if="isActive"></span>
        </div>
        
        <div class="chat-bot-card__body">
            <div class="chat-bot-card__info">
                <div class="info-header">
                    <div class="info-item info-item--primary">
                        <span class="info-label">{{ viewMode === 'list' ? '' : 'Chat ID' }}</span>
                        <span class="info-value" :title="bot.chat_id">{{ bot.chat_id }}</span>
                    </div>
                    <div class="status-badge" :class="`status-badge--${bot.status}`">
                        {{ statusText }}
                    </div>
                </div>
                
                <div class="info-details">
                    <div class="info-item">
                        <span class="info-icon">🏢</span>
                        <span class="info-label" v-if="viewMode === 'grid'">ID объекта:</span>
                        <span class="info-value">{{ bot.object_id }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-icon">💬</span>
                        <span class="info-label" v-if="viewMode === 'grid'">Сообщений:</span>
                        <span class="info-value">{{ messagesCount }}</span>
                    </div>
                    <div class="info-item">
                        <span class="info-icon">🕐</span>
                        <span class="info-label" v-if="viewMode === 'grid'">Последняя активность:</span>
                        <span class="info-value">{{ lastMessageTime }}</span>
                    </div>
                </div>
            </div>
            
            <div class="chat-bot-card__controls">
                <button 
                    class="btn btn--sm"
                    :class="bot.status === 'running' ? 'btn--danger' : 'btn--success'"
                    @click.stop="emit('toggle', bot)"
                >
                    {{ bot.status === 'running' ? '⏸️ Остановить' : '▶️ Запустить' }}
                </button>
            </div>
        </div>
    </div>
</template>

