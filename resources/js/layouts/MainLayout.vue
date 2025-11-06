<script setup lang="ts">
import { useAuth } from '@/composables/useAuth';

const { userName, logout } = useAuth();

const handleLogout = async () => {
  if (confirm('Вы уверены, что хотите выйти?')) {
    await logout();
  }
};
</script>

<template>
  <div class="main-layout">
    <header class="main-header">
      <div class="container">
        <div class="header-content">
          <nav class="header-nav">
            <router-link to="/dashboard" active-class="active">Главная</router-link>
            <router-link to="/chat-bots" active-class="active">Чат боты Ai</router-link>
            <router-link to="/bot-management-ai" active-class="active">WhatsApp Ai</router-link>
            <router-link to="/scenario-bot-sessions" active-class="active">Сессии сценарных ботов</router-link>
            <router-link to="/scenario-bot-management" active-class="active">Сценарий сценарных ботов</router-link>
            <router-link to="/logs" active-class="active">Логи</router-link>
          </nav>
          
          <div class="header-user">
            <button @click="handleLogout" class="btn btn--ghost btn--sm">
              Выход
            </button>
          </div>
        </div>
      </div>
    </header>

    <main class="main-content">
      <slot />
    </main>
  </div>
</template>

