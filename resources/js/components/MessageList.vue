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

/**
 * Конвертирует Markdown форматирование в HTML для отображения
 * Поддерживает WhatsApp-стиль форматирования:
 * **жирный** или *жирный* → <strong>жирный</strong>
 * _курсив_ → <em>курсив</em>
 * ~зачеркнутый~ → <s>зачеркнутый</s>
 * ```код``` → <code>код</code>
 */
const formatMessageContent = (content: string): string => {
    if (!content) return '';
    
    let formatted = content;
    
    // Экранируем HTML теги
    formatted = formatted
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');
    
    // Конвертируем Markdown в HTML
    // 1. Жирный: **текст** или *текст* (одинарные после двойных)
    formatted = formatted.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    formatted = formatted.replace(/\*(.+?)\*/g, '<strong>$1</strong>');
    
    // 2. Курсив: _текст_
    formatted = formatted.replace(/_(.+?)_/g, '<em>$1</em>');
    
    // 3. Зачеркнутый: ~~текст~~ или ~текст~
    formatted = formatted.replace(/~~(.+?)~~/g, '<s>$1</s>');
    formatted = formatted.replace(/~(.+?)~/g, '<s>$1</s>');
    
    // 4. Моноширинный: ```код``` или `код`
    formatted = formatted.replace(/```(.+?)```/g, '<code>$1</code>');
    formatted = formatted.replace(/`(.+?)`/g, '<code>$1</code>');
    
    // 5. Переносы строк
    formatted = formatted.replace(/\n/g, '<br>');
    
    return formatted;
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
                <div class="message__content" v-html="formatMessageContent(message.content)"></div>
                <div class="message__time">
                    {{ formatTime(message.created_at) }}
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.message__content :deep(strong) {
    font-weight: 600;
}

.message__content :deep(em) {
    font-style: italic;
}

.message__content :deep(s) {
    text-decoration: line-through;
}

.message__content :deep(code) {
    background-color: rgba(0, 0, 0, 0.05);
    padding: 2px 4px;
    border-radius: 3px;
    font-family: 'Courier New', monospace;
    font-size: 0.9em;
}

.message--user .message__content :deep(code) {
    background-color: rgba(255, 255, 255, 0.2);
}

.message__content :deep(br) {
    display: block;
    content: "";
    margin-top: 0.5em;
}
</style>

