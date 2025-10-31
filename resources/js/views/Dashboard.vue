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

<script setup lang="ts">
import { computed } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { useAuthStore } from '@/stores/authStore';

const authStore = useAuthStore();
const userName = computed(() => authStore.userName || 'Пользователь');
</script>

<style scoped>
.dashboard {
  max-width: 1200px;
  margin: 0 auto;
  padding: 2rem 1rem;
}

.dashboard-header {
  margin-bottom: 2rem;
  
  h1 {
    font-size: 2rem;
    color: var(--text-primary, #1e293b);
    margin-bottom: 0.5rem;
  }
  
  p {
    color: var(--text-secondary, #64748b);
    font-size: 1.1rem;
  }
}

.stats-grid {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.stat-card {
  background: white;
  padding: 2rem;
  border-radius: 8px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
  transition: transform 0.2s, box-shadow 0.2s;
  
  &:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }
  
  h3 {
    margin: 0 0 1rem 0;
    color: var(--text-secondary, #64748b);
    font-size: 0.9rem;
    text-transform: uppercase;
    font-weight: 600;
  }
  
  .stat-number {
    margin: 0;
    font-size: 2.5rem;
    font-weight: 700;
    color: var(--primary-color, #667eea);
  }
  
  .stat-label {
    font-size: 0.875rem;
    color: var(--text-secondary, #64748b);
  }
}

.dashboard-content {
  .card {
    background: white;
    padding: 2rem;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
    
    h2 {
      margin-top: 0;
      color: var(--text-primary, #1e293b);
    }
    
    p {
      color: var(--text-secondary, #64748b);
      line-height: 1.6;
    }
    
    ul {
      margin: 1rem 0;
      padding-left: 1.5rem;
      
      li {
        margin-bottom: 0.5rem;
        color: var(--text-secondary, #64748b);
      }
    }
  }
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr;
  }
}
</style>

