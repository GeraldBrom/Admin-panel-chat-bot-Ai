<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { useBotStore } from '@/stores/botStore';
import type { BotConfig } from '@/types';

const botStore = useBotStore();

const selectedConfig = ref<BotConfig | null>(null);
const selectedPlatform = ref<'whatsapp'>('whatsapp');

// Load configs when platform changes
watch(selectedPlatform, () => {
    botStore.fetchBotConfigs(selectedPlatform.value);
});

onMounted(() => {
    botStore.fetchBotConfigs(selectedPlatform.value);
});

const configForm = ref({
    prompt: '',
    temperature: 0.7,
    max_tokens: 2000,
});

const loading = computed(() => botStore.loading);

const platformLabels = {
    whatsapp: 'WhatsApp',
};

const platforms = [
    { value: 'whatsapp', label: 'WhatsApp', icon: '📱' },
];

// No per-intent prompts/messages in single-prompt mode

// Removed scenario messages

// No sorting needed

// Select config for editing
const selectConfig = (config: BotConfig) => {
    // Toggle editing mode
    if (selectedConfig.value?.id === config.id) {
        selectedConfig.value = null;
    } else {
        selectedConfig.value = config;
        configForm.value = {
            prompt: config.prompt,
            temperature: config.temperature || 0.7,
            max_tokens: config.max_tokens || 2000,
        };
    }
};

// Save config changes
const saveConfig = async () => {
    if (!selectedConfig.value) return;
    
    try {
        await botStore.updateBotConfig(selectedConfig.value.id, configForm.value);
        await botStore.fetchBotConfigs(selectedPlatform.value);
        selectedConfig.value = null;
    } catch (err) {
        console.error('Failed to save config:', err);
    }
};

// Cancel editing
const cancelEditing = () => {
    selectedConfig.value = null;
};
</script>

<template>
  <MainLayout>
    <div class="bot-management-page">
      <div class="page-header">
        <div>
          <h1>Управление чат ботами</h1>
          <p>Настройка сценариев и промптов для ChatGPT</p>
        </div>
      </div>

      <!-- Main content -->
      <div class="bot-management-content">
        <!-- Platforms list -->
        <div class="platforms-section">
          <h2>Мессенджеры</h2>
          <div class="platforms-list">
            <div
              v-for="platform in platforms"
              :key="platform.value"
              class="platform-item"
              :class="{ 'platform-item--selected': selectedPlatform === platform.value }"
              @click="selectedPlatform = platform.value as any"
            >
              <span class="platform-icon">{{ platform.icon }}</span>
              <span class="platform-label">{{ platform.label }}</span>
            </div>
          </div>
        </div>

        <!-- Selected platform configs -->
        <div class="configs-section">
          <div class="configs-header">
            <h2>Конфигурации для {{ platformLabels[selectedPlatform] }}</h2>
          </div>

          <div v-if="botStore.configs.length === 0" class="empty-state">
            <p>Нет конфигураций для {{ platformLabels[selectedPlatform] }}</p>
          </div>

          <div v-else class="configs-grid">
            <div
              v-for="config in botStore.configs"
              :key="config.id"
              class="config-card"
              :class="{ 'config-card--editing': selectedConfig?.id === config.id }"
            >
              <div class="config-card__header">
                <div class="config-card__title">
                  <h3>{{ config.name }}</h3>
                </div>
                <div class="config-card__actions">
                  <button
                    v-if="selectedConfig?.id !== config.id"
                    class="btn btn--ghost btn--sm"
                    @click="selectConfig(config)"
                  >
                    ✏️ Редактировать
                  </button>
                  <button
                    v-else
                    class="btn btn--ghost btn--sm"
                    @click="cancelEditing"
                  >
                    ❌ Отмена
                  </button>
                </div>
              </div>

              <div class="config-card__body">
                <template v-if="selectedConfig?.id !== config.id">
                  <!-- View mode -->
                  <div class="config-section">
                    <h4>Промпт для ChatGPT</h4>
                    <div class="config-text config-text--pre">{{ config.prompt }}</div>
                  </div>

                  

                  

                  
                </template>

                <template v-else>
                  <!-- Edit mode -->
                  <div class="form-group">
                    <label class="form-label">Промпт для ChatGPT *</label>
                    <textarea
                      v-model="configForm.prompt"
                      class="form-textarea"
                      rows="40"
                      placeholder="Введите системный промпт для ChatGPT..."
                    />
                    <small class="form-help">Этот промпт определяет поведение и стиль ответов бота</small>
                  </div>

                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">Temperature</label>
                      <input
                        v-model.number="configForm.temperature"
                        type="number"
                        class="form-input"
                        min="0"
                        max="2"
                        step="0.1"
                      />
                      <small class="form-help">Контролирует случайность ответов (0-2)</small>
                    </div>

                    <div class="form-group">
                      <label class="form-label">Max Tokens</label>
                      <input
                        v-model.number="configForm.max_tokens"
                        type="number"
                        class="form-input"
                        min="1"
                        max="4000"
                      />
                      <small class="form-help">Максимальная длина ответа</small>
                    </div>
                  </div>

                  

                  <div class="config-card__footer">
                    <button class="btn btn--ghost" @click="cancelEditing">Отмена</button>
                    <button class="btn btn--primary" @click="saveConfig" :disabled="loading">
                      Сохранить
                    </button>
                  </div>
                </template>
              </div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </MainLayout>
</template>
<style scoped>
.config-text--pre {
  white-space: pre-wrap;
  word-break: break-word;
}
</style>
