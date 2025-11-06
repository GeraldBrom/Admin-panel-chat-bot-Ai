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

</script>

<template>
    <div 
        class="chat-bot-card" 
        :class="{ 'chat-bot-card--selected': selected }"
        @click="emit('select', bot)"
    >        
        <div class="chat-bot-card__body">
            <div class="chat-bot-card__info">
                <div class="info-item">
                    <span class="info-label">Телефон:</span>
                    <span class="info-value">{{ bot.chat_id }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Платформа:</span>
                    <span class="info-value"><span class="platform-icon">📱</span>WhatsApp</span>
                </div>
                <div class="info-item">
                    <span class="info-label">ID объекта:</span>
                    <span class="info-value">{{ bot.object_id }}</span>
                </div>
                <div class="info-item">
                    <span class="info-label">Статус:</span>
                    <span class="info-value"><span class="status" :class="statusClass">{{ statusLabel }}</span></span>
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

