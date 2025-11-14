<script setup lang="ts">
import type { ChatBot } from '@/types';

interface Props {
    bot: ChatBot;
    selected?: boolean;
}

withDefaults(defineProps<Props>(), {
    selected: false,
});

const emit = defineEmits<{
    (e: 'select', bot: ChatBot): void;
    (e: 'delete', bot: ChatBot): void;
    (e: 'toggle', bot: ChatBot): void;
}>();

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
                    <span class="info-value">{{ bot.chat_id }}</span>
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

