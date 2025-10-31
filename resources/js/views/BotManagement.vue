<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { useBotStore } from '@/stores/botStore';
import type { BotConfig } from '@/types';

const botStore = useBotStore();

const selectedConfig = ref<BotConfig | null>(null);
const selectedPlatform = ref<'whatsapp' | 'telegram' | 'max'>('max');

// Load configs when platform changes
watch(selectedPlatform, () => {
    botStore.fetchBotConfigs(selectedPlatform.value);
});

onMounted(() => {
    botStore.fetchBotConfigs(selectedPlatform.value);
});

const configForm = ref({
    prompt: '',
    scenario_description: '',
    temperature: 0.7,
    max_tokens: 2000,
    settings: {} as Record<string, any>,
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

// Localization for prompts and messages
const promptLabels: Record<string, string> = {
    'analyze_response': 'Анализ ответов клиентов',
    'is_objection': 'Проверка возражений',
    'handle_objection': 'Обработка возражений',
};

const messageLabels: Record<string, string> = {
    'greeting': 'Приветствие',
    'initial_question_no_deals': 'Вопрос без сделок',
    'initial_question_with_deals': 'Вопрос со сделками',
    'price_confirmation_positive': 'Подтверждение цены (да)',
    'price_confirmation_negative': 'Подтверждение цены (нет)',
    'price_update_invalid': 'Неверная цена',
    'price_update_success': 'Обновление цены',
    'commission_info_positive': 'Информация о комиссии',
    'final_success': 'Успешное завершение',
    'final_negative': 'Негативное завершение',
    'negative_intent': 'Негативный настрой',
    'pause': 'Пауза',
};

// Order for messages (scenario flow)
const messageOrder = [
    'greeting',
    'initial_question_no_deals',
    'initial_question_with_deals',
    'price_confirmation_positive',
    'price_confirmation_negative',
    'price_update_invalid',
    'price_update_success',
    'commission_info_positive',
    'final_success',
    'final_negative',
    'negative_intent',
    'pause',
];

// Helper function to sort by order
const sortMessages = (messages: Record<string, string>) => {
    const sorted: Record<string, string> = {};
    messageOrder.forEach(key => {
        if (messages[key]) {
            sorted[key] = messages[key];
        }
    });
    // Add any remaining keys not in the order
    Object.keys(messages).forEach(key => {
        if (!sorted[key]) {
            sorted[key] = messages[key];
        }
    });
    return sorted;
};

// Select config for editing
const selectConfig = (config: BotConfig) => {
    // Toggle editing mode
    if (selectedConfig.value?.id === config.id) {
        selectedConfig.value = null;
    } else {
        selectedConfig.value = config;
        configForm.value = {
            prompt: config.prompt,
            scenario_description: config.scenario_description,
            temperature: config.temperature || 0.7,
            max_tokens: config.max_tokens || 2000,
            settings: config.settings || {},
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
              :class="{ 'config-card--active': config.is_active, 'config-card--editing': selectedConfig?.id === config.id }"
            >
              <div class="config-card__header">
                <div class="config-card__title">
                  <h3>{{ config.name }}</h3>
                  <span v-if="config.is_active" class="badge badge--active">Активная</span>
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
                    <p class="config-text">{{ config.prompt }}</p>
                  </div>

                  <div class="config-section">
                    <h4>Сценарий</h4>
                    <p class="config-text">{{ config.scenario_description }}</p>
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

                  <!-- Show prompts and messages settings -->
                  <div v-if="config.settings?.prompts" class="config-section">
                    <h4>Промпты для анализа</h4>
                    <div class="config-params">
                      <div class="param" v-for="(value, key) in config.settings.prompts" :key="key">
                        <span class="param-label">{{ promptLabels[key] || key }}:</span>
                        <span class="param-value param-value--truncate">{{ value.substring(0, 50) }}...</span>
                      </div>
                    </div>
                  </div>

                  <div v-if="config.settings?.messages" class="config-section">
                    <h4>Шаблоны сообщений</h4>
                    <div class="config-params">
                      <div class="param" v-for="(value, key) in sortMessages(config.settings.messages)" :key="key">
                        <span class="param-label">{{ messageLabels[key] || key }}:</span>
                        <span class="param-value param-value--full">{{ value }}</span>
                      </div>
                    </div>
                  </div>
                </template>

                <template v-else>
                  <!-- Edit mode -->
                  <div class="form-group">
                    <label class="form-label">Промпт для ChatGPT *</label>
                    <textarea
                      v-model="configForm.prompt"
                      class="form-textarea"
                      rows="5"
                      placeholder="Введите системный промпт для ChatGPT..."
                    />
                    <small class="form-help">Этот промпт определяет поведение и стиль ответов бота</small>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Сценарий *</label>
                    <textarea
                      v-model="configForm.scenario_description"
                      class="form-textarea"
                      rows="5"
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

                  <!-- Edit Prompts -->
                  <div v-if="configForm.settings?.prompts" class="config-section config-section--editable">
                    <h4>Промпты для анализа</h4>
                    <div class="form-group" v-for="(value, key) in configForm.settings.prompts" :key="key">
                      <label class="form-label">{{ promptLabels[key] || key }}</label>
                      <textarea
                        v-model="configForm.settings.prompts[key]"
                        class="form-textarea"
                        rows="8"
                        :placeholder="`Введите промпт для ${promptLabels[key] || key}...`"
                      />
                    </div>
                  </div>

                  <!-- Edit Messages -->
                  <div v-if="configForm.settings?.messages" class="config-section config-section--editable">
                    <h4>Шаблоны сообщений</h4>
                    <div class="form-group" v-for="(value, key) in sortMessages(configForm.settings.messages)" :key="key">
                      <label class="form-label">{{ messageLabels[key] || key }}</label>
                      <textarea
                        v-model="configForm.settings.messages[key]"
                        class="form-textarea"
                        rows="3"
                        :placeholder="`Введите шаблон для ${messageLabels[key] || key}...`"
                      />
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
