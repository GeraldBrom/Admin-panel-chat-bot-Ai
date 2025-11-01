<script setup lang="ts">
import { computed } from 'vue';
import type { BotSession } from '@/types';

interface Props {
    sessions: BotSession[];
    selectedSessionId?: number;
}

withDefaults(defineProps<Props>(), {
    selectedSessionId: undefined,
});

const emit = defineEmits<{
    (e: 'select', session: BotSession): void;
    (e: 'pause', session: BotSession): void;
    (e: 'resume', session: BotSession): void;
}>();

const statusClass = computed(() => (session: BotSession) => {
    switch (session.status) {
        case 'running':
            return 'status--active';
        case 'paused':
            return 'status--paused';
        default:
            return 'status--inactive';
    }
});

const statusLabel = computed(() => (session: BotSession) => {
    switch (session.status) {
        case 'running':
            return 'Активна';
        case 'paused':
            return 'Приостановлена';
        case 'stopped':
            return 'Остановлена';
        case 'completed':
            return 'Завершена';
        default:
            return 'Неактивна';
    }
});

const formatDate = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleString('ru-RU', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    });
};
</script>

<template>
    <div class="session-list">
        <div v-if="sessions.length === 0" class="empty-state">
            <p>Нет активных сессий</p>
        </div>
        
        <div v-else class="session-list__items">
            <div
                v-for="session in sessions"
                :key="session.id"
                class="session-item"
                :class="{ 'session-item--selected': selectedSessionId === session.id }"
                @click="emit('select', session)"
            >
                <div class="session-item__header">
                    <div class="session-item__info">
                        <span class="session-key">{{ session.chat_id }}</span>
                        <span class="status" :class="statusClass(session)">
                            {{ statusLabel(session) }}
                        </span>
                    </div>
                    <div class="session-item__actions">
                        <button
                            v-if="session.status === 'running'"
                            class="btn btn--ghost btn--sm"
                            @click.stop="emit('pause', session)"
                        >
                            ⏸️
                        </button>
                        <button
                            v-else-if="session.status === 'paused'"
                            class="btn btn--ghost btn--sm"
                            @click.stop="emit('resume', session)"
                        >
                            ▶️
                        </button>
                    </div>
                </div>
                
                <div class="session-item__body">
                    <div class="session-item__stats">
                        <div class="stat">
                            <span class="stat-number">{{ session.messages?.length || 0 }}</span>
                            <span class="stat-label">Сообщений</span>
                        </div>
                        <div class="stat">
                            <span class="stat-date">{{ formatDate(session.updated_at) }}</span>
                            <span class="stat-label">Обновлена</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

