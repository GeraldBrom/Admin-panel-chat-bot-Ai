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
          <div class="header-logo">
            <router-link to="/dashboard">
              <img src="/images/logo.png" alt="Logo" class="header-logo-img" width="130">
            </router-link>
          </div>
          
          <nav class="header-nav">
            <router-link to="/dashboard" active-class="active">Главная</router-link>
            <router-link to="/chat-bots" active-class="active">Чат боты</router-link>
            <router-link to="/bot-management" active-class="active">Управление ботами</router-link>
            <router-link to="/logs" active-class="active">Логи</router-link>
          </nav>
          
          <div class="header-user">
            <span class="user-name">{{ userName }}</span>
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

