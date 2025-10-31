<script setup lang="ts">
import { ref, computed } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { useBotStore } from '@/stores/botStore';
import type { BotConfig } from '@/types';

const botStore = useBotStore();

const selectedConfig = ref<BotConfig | null>(null);
const showEditModal = ref(false);
const selectedPlatform = ref<'whatsapp' | 'telegram' | 'max'>('max');
const activeTab = ref<'prompt' | 'scenario'>('prompt');

const configForm = ref({
    prompt: '',
    scenario: '',
    temperature: 0.7,
    max_tokens: 2000,
});

const loading = computed(() => botStore.loading);

const platformLabels = {
    whatsapp: 'WhatsApp',
    telegram: 'Telegram',
    max: 'MAX',
};

const platforms = [
    { value: 'max', label: 'MAX', icon: '🤖' },
    { value: 'whatsapp', label: 'WhatsApp', icon: '📱' },
    { value: 'telegram', label: 'Telegram', icon: '✈️' },
];

// Select config for editing
const selectConfig = (config: BotConfig) => {
    selectedConfig.value = config;
    configForm.value = {
        prompt: config.prompt,
        scenario: config.scenario,
        temperature: config.temperature || 0.7,
        max_tokens: config.max_tokens || 2000,
    };
    showEditModal.value = true;
};

// Save config changes
const saveConfig = async () => {
    if (!selectedConfig.value) return;
    
    try {
        await botStore.updateBotConfig(selectedConfig.value.id, configForm.value);
        showEditModal.value = false;
        selectedConfig.value = null;
    } catch (err) {
        console.error('Failed to save config:', err);
    }
};

// Close modal
const closeModal = () => {
    showEditModal.value = false;
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

          <!-- Tabs -->
          <div class="tabs">
            <button
              class="tab"
              :class="{ 'tab--active': activeTab === 'prompt' }"
              @click="activeTab = 'prompt'"
            >
              Промпт для ChatGPT
            </button>
            <button
              class="tab"
              :class="{ 'tab--active': activeTab === 'scenario' }"
              @click="activeTab = 'scenario'"
            >
              Сценарий
            </button>
          </div>

          <div v-if="botStore.configs.length === 0" class="empty-state">
            <p>Нет конфигураций для {{ platformLabels[selectedPlatform] }}</p>
          </div>

          <div v-else class="configs-grid">
            <div
              v-for="config in botStore.configs"
              :key="config.id"
              class="config-card"
              :class="{ 'config-card--active': config.is_active }"
            >
              <div class="config-card__header">
                <div class="config-card__title">
                  <h3>{{ config.name }}</h3>
                  <span v-if="config.is_active" class="badge badge--active">Активная</span>
                </div>
                <div class="config-card__actions">
                  <button
                    class="btn btn--ghost btn--sm"
                    @click="selectConfig(config)"
                  >
                    ✏️ Редактировать
                  </button>
                </div>
              </div>

              <div class="config-card__body">
                <div class="config-section">
                  <h4>Промпт для ChatGPT</h4>
                  <p class="config-text">{{ config.prompt }}</p>
                </div>

                <div class="config-section">
                  <h4>Сценарий</h4>
                  <p class="config-text">{{ config.scenario }}</p>
                </div>

                <div class="config-section">
                  <h4>Параметры</h4>
                  <div class="config-params">
                    <div class="param">
                      <span class="param-label">Temperature:</span>
                      <span class="param-value">{{ config.temperature }}</span>
                    </div>
                    <div class="param">
                      <span class="param-label">Max tokens:</span>
                      <span class="param-value">{{ config.max_tokens }}</span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Edit config modal -->
      <div v-if="showEditModal" class="modal-overlay" @click="closeModal">
        <div class="modal modal--large" @click.stop>
          <div class="modal__header">
            <h3>Редактировать конфигурацию</h3>
            <button class="btn btn--ghost btn--sm" @click="closeModal">✕</button>
          </div>
          <div class="modal__body">
            <div class="form-group">
              <label class="form-label">Промпт для ChatGPT *</label>
              <textarea
                v-model="configForm.prompt"
                class="form-textarea"
                rows="8"
                placeholder="Введите системный промпт для ChatGPT..."
              />
              <small class="form-help">Этот промпт определяет поведение и стиль ответов бота</small>
            </div>

            <div class="form-group">
              <label class="form-label">Сценарий *</label>
              <textarea
                v-model="configForm.scenario"
                class="form-textarea"
                rows="8"
                placeholder="Опишите сценарий диалога..."
              />
              <small class="form-help">Сценарий определяет логику и последовательность диалога</small>
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
          </div>
          <div class="modal__footer">
            <button class="btn btn--ghost" @click="closeModal">Отмена</button>
            <button class="btn btn--primary" @click="saveConfig" :disabled="loading">
              Сохранить
            </button>
          </div>
        </div>
      </div>
    </div>
  </MainLayout>
</template>
