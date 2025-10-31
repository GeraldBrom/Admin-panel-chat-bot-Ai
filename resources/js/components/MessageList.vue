<script setup lang="ts">
import { onMounted, ref, watch } from 'vue';
import type { Message } from '@/types';

interface Props {
    messages: Message[];
}

const props = defineProps<Props>();

const messagesContainer = ref<HTMLElement>();

const scrollToBottom = () => {
    if (messagesContainer.value) {
        messagesContainer.value.scrollTop = messagesContainer.value.scrollHeight;
    }
};

const formatTime = (dateString: string) => {
    const date = new Date(dateString);
    return date.toLocaleTimeString('ru-RU', {
        hour: '2-digit',
        minute: '2-digit',
    });
};

const getMessageSender = (role: string): string => {
    // Map role to sender class for styling
    if (role === 'user') return 'user';
    if (role === 'assistant') return 'bot';
    return 'bot'; // Default to bot for system and other roles
};

watch(() => props.messages, () => {
    scrollToBottom();
}, { deep: true });

onMounted(() => {
    scrollToBottom();
});
</script>

<template>
    <div class="message-list" ref="messagesContainer">
        <div v-if="messages.length === 0" class="empty-state">
            <p>Нет сообщений</p>
        </div>
        
        <div v-else class="message-list__items">
            <div
                v-for="message in messages"
                :key="message.id"
                class="message"
                :class="`message--${getMessageSender(message.role)}`"
            >
                <div class="message__content">
                    {{ message.content }}
                </div>
                <div class="message__time">
                    {{ formatTime(message.created_at) }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped lang="scss">
@import '@css/abstracts/variables';
@import '@css/abstracts/mixins';

.message-list {
    flex: 1;
    overflow-y: auto;
    padding: $spacing-md;
    background: $bg-gray-50;
    
    &__items {
        display: flex;
        flex-direction: column;
        gap: $spacing-md;
    }
}

.message {
    display: flex;
    flex-direction: column;
    max-width: 70%;
    padding: $spacing-md;
    border-radius: $border-radius;
    
    &__content {
        word-wrap: break-word;
        line-height: 1.5;
    }
    
    &__time {
        font-size: $font-size-xs;
        color: $text-secondary;
        margin-top: $spacing-xs;
    }
    
    &--user {
        align-self: flex-end;
        background: $primary-color;
        color: $text-white;
        
        .message__time {
            color: rgba($text-white, 0.8);
        }
    }
    
    &--bot {
        align-self: flex-start;
        background: $bg-white;
        border: 1px solid $border-color;
        color: $text-primary;
    }
}

.empty-state {
    display: flex;
    align-items: center;
    justify-content: center;
    height: 100%;
    color: $text-secondary;
}
</style>

