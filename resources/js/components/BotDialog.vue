<script setup lang="ts">
import { ref } from 'vue';
import MessageList from './MessageList.vue';
import type { Message } from '@/types';

interface Props {
    messages: Message[];
    loading?: boolean;
    disabled?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    disabled: false,
});

const emit = defineEmits<{
    (e: 'send', message: string): void;
}>();

const messageInput = ref('');
const sendBtn = ref<HTMLButtonElement>();

const sendMessage = () => {
    if (!messageInput.value.trim() || props.disabled || props.loading) {
        return;
    }
    
    emit('send', messageInput.value);
    messageInput.value = '';
};

const handleKeyPress = (event: KeyboardEvent) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        sendMessage();
    }
};
</script>

<template>
    <div class="bot-dialog">
        <slot name="messages">
            <MessageList :messages="messages" />
        </slot>
        
        <div class="bot-dialog__input">
            <textarea
                v-model="messageInput"
                class="message-input"
                :disabled="disabled || loading"
                placeholder="Введите сообщение..."
                @keydown="handleKeyPress"
            />
            <button
                ref="sendBtn"
                class="btn btn--primary"
                :disabled="!messageInput.trim() || disabled || loading"
                @click="sendMessage"
            >
                <span v-if="!loading">Отправить</span>
                <span v-else>Отправка...</span>
            </button>
        </div>
    </div>
</template>

<style scoped lang="scss">
@import '@css/abstracts/variables';
@import '@css/abstracts/mixins';

.bot-dialog {
    display: flex;
    flex-direction: column;
    height: 100%;
    flex: 1;
    
    &__input {
        display: flex;
        gap: $spacing-md;
        padding: $spacing-md;
        background: $bg-white;
        border-top: 1px solid $border-color;
    }
}

.message-input {
    flex: 1;
    min-height: 50px;
    max-height: 150px;
    padding: $spacing-md;
    border: 1px solid $border-color;
    border-radius: $border-radius;
    font-size: $font-size-base;
    font-family: $font-family;
    resize: none;
    transition: border-color $transition-base;
    
    &:focus {
        outline: none;
        border-color: $primary-color;
    }
    
    &:disabled {
        background: $bg-gray-100;
        cursor: not-allowed;
    }
}
</style>

