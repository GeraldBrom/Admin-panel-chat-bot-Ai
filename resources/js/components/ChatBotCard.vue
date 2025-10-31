<script setup lang="ts">
import { computed } from 'vue';
import type { ChatBot } from '@/types';

interface Props {
    bot: ChatBot;
    selected?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    selected: false,
});

const emit = defineEmits<{
    (e: 'select', bot: ChatBot): void;
    (e: 'edit', bot: ChatBot): void;
    (e: 'delete', bot: ChatBot): void;
    (e: 'toggle', bot: ChatBot): void;
}>();

const statusClass = computed(() => {
    switch (props.bot.status) {
        case 'running':
            return 'status--online';
        case 'completed':
            return 'status--processing';
        default:
            return 'status--offline';
    }
});

const statusLabel = computed(() => {
    switch (props.bot.status) {
        case 'running':
            return 'Работает';
        case 'completed':
            return 'Завершен';
        default:
            return 'Остановлен';
    }
});

const platformIcon = computed(() => {
    const icons: Record<string, string> = {
        whatsapp: '📱',
        telegram: '✈️',
        max: '🤖',
    };
    return icons[props.bot.platform] || '🤖';
});

const platformLabel = computed(() => {
    const labels: Record<string, string> = {
        whatsapp: 'WhatsApp',
        telegram: 'Telegram',
        max: 'MAX',
    };
    return labels[props.bot.platform] || 'Unknown';
});
</script>

<template>
    <div 
        class="chat-bot-card" 
        :class="{ 'chat-bot-card--selected': selected }"
        @click="emit('select', bot)"
    >
        <div class="chat-bot-card__header">
            <div class="chat-bot-card__title">
                <h3>{{ bot.chat_id }}</h3>
                <span class="status" :class="statusClass">{{ statusLabel }}</span>
            </div>
            <div class="chat-bot-card__actions">
                <button 
                    class="btn btn--ghost btn--sm" 
                    @click.stop="emit('edit', bot)"
                >
                    ✏️
                </button>
                <button 
                    class="btn btn--ghost btn--sm btn--danger" 
                    @click.stop="emit('delete', bot)"
                >
                    🗑️
                </button>
            </div>
        </div>
        
        <div class="chat-bot-card__body">
            <div class="chat-bot-card__info">
                <div class="info-item">
                    <span class="info-label">Платформа:</span>
                    <span class="info-value">
                        <span class="platform-icon">{{ platformIcon }}</span>
                        {{ platformLabel }}
                    </span>
                </div>
                <div class="info-item">
                    <span class="info-label">ID объекта:</span>
                    <span class="info-value">{{ bot.object_id }}</span>
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
</template>

<style scoped lang="scss">
@import '@css/abstracts/variables';
@import '@css/abstracts/mixins';

.chat-bot-card {
    @include card;
    cursor: pointer;
    transition: all $transition-base;
    
    &:hover {
        box-shadow: $box-shadow-lg;
        transform: translateY(-2px);
    }
    
    &--selected {
        border: 2px solid $primary-color;
    }
    
    &__header {
        @include flex-between;
        margin-bottom: $spacing-md;
        padding-bottom: $spacing-md;
        border-bottom: 1px solid $border-color;
    }
    
    &__title {
        display: flex;
        align-items: center;
        gap: $spacing-md;
        
        h3 {
            margin: 0;
            font-size: $font-size-lg;
            color: $text-primary;
        }
    }
    
    &__actions {
        display: flex;
        gap: $spacing-xs;
    }
    
    &__body {
        margin-bottom: $spacing-md;
    }
    
    &__info {
        display: flex;
        flex-direction: column;
        gap: $spacing-sm;
    }
    
    &__footer {
        display: flex;
        gap: $spacing-lg;
        padding-top: $spacing-md;
        border-top: 1px solid $border-color;
    }
}

.status {
    padding: $spacing-xs $spacing-md;
    border-radius: $border-radius-sm;
    font-size: $font-size-sm;
    font-weight: 600;
    text-transform: uppercase;
    
    &--online {
        background: rgba($success-color, 0.1);
        color: $success-color;
    }
    
    &--processing {
        background: rgba($warning-color, 0.1);
        color: $warning-color;
    }
    
    &--offline {
        background: rgba($text-secondary, 0.1);
        color: $text-secondary;
    }
}

.info-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    
    .info-label {
        color: $text-secondary;
        font-size: $font-size-sm;
    }
    
    .info-value {
        color: $text-primary;
        font-weight: 500;
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
}

.platform-icon {
    font-size: 1.25rem;
}

.stat-item {
    display: flex;
    flex-direction: column;
    align-items: center;
    
    .stat-number {
        font-size: $font-size-xl;
        font-weight: 700;
        color: $primary-color;
    }
    
    .stat-label {
        font-size: $font-size-xs;
        color: $text-secondary;
    }
}

.btn--danger:hover {
    color: $danger-color;
}

.chat-bot-card__controls {
    padding-top: $spacing-md;
    margin-top: $spacing-md;
    border-top: 1px solid $border-color;
    
    .btn {
        width: 100%;
        justify-content: center;
    }
}
</style>

