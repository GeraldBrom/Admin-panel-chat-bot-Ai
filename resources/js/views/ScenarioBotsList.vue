<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useRouter } from 'vue-router';
import MainLayout from '@/layouts/MainLayout.vue';
import { useScenarioBotStore } from '@/stores/scenarioBotStore';

const router = useRouter();
const scenarioBotStore = useScenarioBotStore();

const loading = computed(() => scenarioBotStore.loading);
const error = computed(() => scenarioBotStore.error);
const bots = computed(() => scenarioBotStore.scenarioBots);

onMounted(async () => {
    await scenarioBotStore.fetchAllScenarioBots();
});

const editBot = () => {
    router.push({ name: 'scenario-bot-management' });
};

const getPlatformIcon = (platform: string) => {
    return {
        'whatsapp': '💬',
        'telegram': '✈️',
        'max': '📱',
    }[platform] || '🤖';
};

const getStatusBadge = (isActive: boolean) => {
    return isActive ? 'Активен' : 'Неактивен';
};
</script>

<template>
  <MainLayout>
    <div class="scenario-bots-page">
      <div class="page-header">
        <div>
          <h1>🤖 Сценарные боты</h1>
          <p>Выберите бота для редактирования сценария</p>
        </div>
      </div>

      <div v-if="error" class="alert alert--danger">
        {{ error }}
      </div>

      <div v-if="loading && bots.length === 0" class="empty-state">
        <div class="loader"></div>
        <p>Загрузка ботов...</p>
      </div>

      <div v-else-if="bots.length === 0" class="empty-state">
        <div class="empty-icon">🤖</div>
        <h3>Нет сценарных ботов</h3>
        <p>Создайте бота через API или базу данных</p>
      </div>

      <div v-else>
        <div class="manage-scenario-button">
          <button class="btn btn--primary btn--large" @click="editBot">
            ✏️ Управлять общим сценарием
          </button>
        </div>
        
        <div class="bots-grid">
        <div v-for="bot in bots" :key="bot.id" class="bot-card">
          <div class="bot-card__header">
            <div class="bot-card__title">
              <span class="bot-icon">{{ getPlatformIcon(bot.platform) }}</span>
              <h3>{{ bot.name }}</h3>
            </div>
            <div class="bot-card__badge" :class="{ 'badge--active': bot.is_active }">
              {{ getStatusBadge(bot.is_active) }}
            </div>
          </div>

          <div class="bot-card__body">
            <p v-if="bot.description" class="bot-description">
              {{ bot.description }}
            </p>
            <p v-else class="bot-description bot-description--empty">
              Описание отсутствует
            </p>

            <div class="bot-stats">
              <div class="bot-stat">
                <span class="bot-stat__label">Платформа:</span>
                <span class="bot-stat__value">{{ bot.platform }}</span>
              </div>
              <div class="bot-stat">
                <span class="bot-stat__label">Шагов:</span>
                <span class="bot-stat__value">{{ bot.steps?.length || 0 }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>

<style scoped lang="scss">
.scenario-bots-page {
  padding: 2rem;
}

.page-header {
  display: flex;
  justify-content: space-between;
  align-items: center;
  margin-bottom: 2rem;
  padding-bottom: 1.5rem;
  border-bottom: 1px solid #e0e0e0;

  h1 {
    font-size: 1.875rem;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #1a1a1a;
  }

  p {
    color: #666;
    font-size: 0.95rem;
  }
}

.page-actions {
  display: flex;
  gap: 1rem;
}

.alert {
  padding: 1rem;
  border-radius: 6px;
  margin-bottom: 1.5rem;

  &--danger {
    background: #fee;
    color: #c33;
    border: 1px solid #fcc;
  }
}

.empty-state {
  text-align: center;
  padding: 4rem 2rem;
  color: #666;

  .loader {
    margin: 0 auto 1rem;
    width: 50px;
    height: 50px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }

  .empty-icon {
    font-size: 4rem;
    margin-bottom: 1rem;
  }

  h3 {
    font-size: 1.5rem;
    margin-bottom: 0.5rem;
    color: #333;
  }

  p {
    margin-bottom: 1.5rem;
  }
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.bots-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(350px, 1fr));
  gap: 1.5rem;
}

.bot-card {
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
  overflow: hidden;
  transition: box-shadow 0.2s;
  cursor: pointer;

  &:hover {
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
  }

  &__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 1.25rem;
    border-bottom: 1px solid #f0f0f0;
  }

  &__title {
    display: flex;
    align-items: center;
    gap: 0.75rem;

    h3 {
      font-size: 1.1rem;
      font-weight: 600;
      color: #333;
      margin: 0;
    }
  }

  &__badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 500;
    background: #e0e0e0;
    color: #666;

    &.badge--active {
      background: #d4edda;
      color: #155724;
    }
  }

  &__body {
    padding: 1.25rem;
  }
}

.bot-icon {
  font-size: 1.5rem;
}

.bot-description {
  color: #666;
  font-size: 0.9rem;
  line-height: 1.5;
  margin-bottom: 1rem;

  &--empty {
    font-style: italic;
    color: #999;
  }
}

.bot-stats {
  display: flex;
  gap: 1.5rem;
}

.bot-stat {
  &__label {
    font-size: 0.8rem;
    color: #999;
    margin-right: 0.5rem;
  }

  &__value {
    font-weight: 600;
    color: #333;
  }
}

.manage-scenario-button {
  margin-bottom: 2rem;
  text-align: center;
  padding: 2rem;
  background: white;
  border-radius: 8px;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.btn--large {
  padding: 1rem 2rem;
  font-size: 1.1rem;
}
</style>

