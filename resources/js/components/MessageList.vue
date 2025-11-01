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
    if (role === 'user') return 'user';
    if (role === 'assistant') return 'bot';
    return 'bot';
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

