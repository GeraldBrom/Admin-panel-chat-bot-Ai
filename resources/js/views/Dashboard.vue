<script setup lang="ts">
import { computed } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { useAuthStore } from '@/stores/authStore';

const authStore = useAuthStore();
const userName = computed(() => authStore.userName || 'Пользователь');
</script>

<template>
  <MainLayout>
    <div class="dashboard">
      <div class="dashboard-header">
        <h1>Панель управления</h1>
        <p>Добро пожаловать, {{ userName }}!</p>
      </div>

      <div class="stats-grid">
        <div class="stat-card">
          <h3>Всего ботов</h3>
          <p class="stat-number">0</p>
          <span class="stat-label">Активных</span>
        </div>
        
        <div class="stat-card">
          <h3>Сообщений сегодня</h3>
          <p class="stat-number">0</p>
          <span class="stat-label">За последние 24 часа</span>
        </div>
        
        <div class="stat-card">
          <h3>Активных пользователей</h3>
          <p class="stat-number">1</p>
          <span class="stat-label">Онлайн сейчас</span>
        </div>
      </div>

      <div class="dashboard-content">
        <div class="card">
          <h2>Быстрый старт</h2>
          <p>Добро пожаловать в систему управления чат-ботами! Здесь вы можете:</p>
          <ul>
            <li>Создавать и настраивать чат-ботов</li>
            <li>Отслеживать статистику сообщений</li>
            <li>Управлять пользователями</li>
            <li>Просматривать аналитику</li>
          </ul>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

