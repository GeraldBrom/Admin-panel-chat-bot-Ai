<script setup lang="ts">
import { computed } from 'vue';
import type { BotSession } from '@/types';

interface Props {
    sessions: BotSession[];
    selectedSessionId?: number;
}

const props = withDefaults(defineProps<Props>(), {
    selectedSessionId: undefined,
});

const emit = defineEmits<{
    (e: 'select', session: BotSession): void;
    (e: 'pause', session: BotSession): void;
    (e: 'resume', session: BotSession): void;
}>();

const statusClass = computed(() => (session: BotSession) => {
    switch (session.status) {
        case 'active':
            return 'status--active';
        case 'paused':
            return 'status--paused';
        default:
            return 'status--inactive';
    }
});

const statusLabel = computed(() => (session: BotSession) => {
    switch (session.status) {
        case 'active':
            return 'Активна';
        case 'paused':
            return 'Приостановлена';
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
                        <span class="session-key">{{ session.session_key }}</span>
                        <span class="status" :class="statusClass(session)">
                            {{ statusLabel(session) }}
                        </span>
                    </div>
                    <div class="session-item__actions">
                        <button
                            v-if="session.status === 'active'"
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
                            <span class="stat-number">{{ session.message_count }}</span>
                            <span class="stat-label">Сообщений</span>
                        </div>
                        <div class="stat">
                            <span class="stat-date">{{ formatDate(session.last_message_at) }}</span>
                            <span class="stat-label">Последнее</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
@import '@css/abstracts/variables';
@import '@css/abstracts/mixins';

.session-list {
    &__items {
        display: flex;
        flex-direction: column;
        gap: $spacing-md;
    }
}

.session-item {
    @include card;
    cursor: pointer;
    transition: all $transition-base;
    
    &:hover {
        box-shadow: $box-shadow-lg;
    }
    
    &--selected {
        border: 2px solid $primary-color;
        background: rgba($primary-color, 0.05);
    }
    
    &__header {
        @include flex-between;
        margin-bottom: $spacing-md;
        padding-bottom: $spacing-md;
        border-bottom: 1px solid $border-color;
    }
    
    &__info {
        display: flex;
        flex-direction: column;
        gap: $spacing-xs;
    }
    
    &__body {
        display: flex;
        justify-content: space-between;
    }
    
    &__stats {
        display: flex;
        gap: $spacing-lg;
        width: 100%;
    }
    
    &__actions {
        display: flex;
        gap: $spacing-xs;
    }
}

.session-key {
    font-weight: 600;
    color: $text-primary;
    font-size: $font-size-sm;
}

.status {
    padding: $spacing-xs $spacing-sm;
    border-radius: $border-radius-sm;
    font-size: $font-size-xs;
    font-weight: 600;
    display: inline-block;
    width: fit-content;
    
    &--active {
        background: rgba($success-color, 0.1);
        color: $success-color;
    }
    
    &--paused {
        background: rgba($warning-color, 0.1);
        color: $warning-color;
    }
    
    &--inactive {
        background: rgba($text-secondary, 0.1);
        color: $text-secondary;
    }
}

.stat {
    display: flex;
    flex-direction: column;
    
    .stat-number {
        font-size: $font-size-lg;
        font-weight: 700;
        color: $primary-color;
    }
    
    .stat-date {
        font-size: $font-size-sm;
        font-weight: 500;
        color: $text-primary;
    }
    
    .stat-label {
        font-size: $font-size-xs;
        color: $text-secondary;
        margin-top: $spacing-xs;
    }
}

.empty-state {
    text-align: center;
    padding: $spacing-2xl;
    color: $text-secondary;
}
</style>

