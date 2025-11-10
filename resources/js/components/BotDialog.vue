<script setup lang="ts">
import MessageList from './MessageList.vue';
import type { Message } from '@/types';

interface Props {
    messages: Message[];
}

defineProps<Props>();
</script>

<template>
    <div class="bot-dialog">
        <slot name="messages">
            <MessageList :messages="messages" />
        </slot>
    </div>
</template>

