<script setup lang="ts">
import { computed } from 'vue';
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
            <router-link to="/dashboard">CapitalMars Chat-Bot</router-link>
          </div>
          
          <nav class="header-nav">
            <router-link to="/dashboard" active-class="active">Главная</router-link>
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

    <footer class="main-footer">
      <div class="container">
        <p>&copy; 2024 Admin Panel Chat Bot. Все права защищены.</p>
      </div>
    </footer>
  </div>
</template>

