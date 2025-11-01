<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { useBotStore } from '@/stores/botStore';
import type { BotConfig, VectorStore } from '@/types';

const botStore = useBotStore();

const selectedConfig = ref<BotConfig | null>(null);
const selectedPlatform = ref<'whatsapp'>('whatsapp');

watch(selectedPlatform, () => {
    botStore.fetchBotConfigs(selectedPlatform.value);
});

onMounted(() => {
    botStore.fetchBotConfigs(selectedPlatform.value);
});

const configForm = ref<{
    prompt: string;
    temperature: number;
    max_tokens: number;
    kickoff_message: string;
    vector_stores: VectorStore[];
    openai_model: string;
    openai_service_tier: string;
}>({
    prompt: '',
    temperature: 0.7,
    max_tokens: 2000,
    kickoff_message: '',
    vector_stores: [],
    openai_model: 'gpt-5-2025-08-07',
    openai_service_tier: 'flex',
});

const loading = computed(() => botStore.loading);

const platformLabels = {
    whatsapp: 'WhatsApp',
};

const platforms = [
    { value: 'whatsapp', label: 'WhatsApp', icon: '📱' },
];

const selectConfig = (config: BotConfig) => {
    if (selectedConfig.value?.id === config.id) {
        selectedConfig.value = null;
    } else {
        selectedConfig.value = config;
        configForm.value = {
            prompt: config.prompt,
            temperature: config.temperature || 0.7,
            max_tokens: config.max_tokens || 2000,
            kickoff_message: config.kickoff_message || '',
            vector_stores: config.vector_stores ? [...config.vector_stores] : [],
            openai_model: config.openai_model || 'gpt-5-2025-08-07',
            openai_service_tier: config.openai_service_tier || 'flex',
        };
    }
};

const addVectorStore = () => {
    configForm.value.vector_stores.push({
        name: '',
        id: '',
    });
};

const removeVectorStore = (index: number) => {
    configForm.value.vector_stores.splice(index, 1);
};

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

      <div class="bot-management-content">
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

                  <div class="config-section">
                    <h4>Приветственное сообщение (Kickoff)</h4>
                    <div class="config-text config-text--pre">{{ config.kickoff_message || 'Не задано' }}</div>
                  </div>

                  <div class="config-section">
                    <h4>Промпт для ChatGPT</h4>
                    <div class="config-text config-text--pre">{{ config.prompt }}</div>
                  </div>

                  

                  <div class="config-section">
                    <h4>Vector Stores (базы знаний RAG)</h4>
                    <div v-if="config.vector_stores && config.vector_stores.length > 0" class="vector-stores-list">
                      <div v-for="(store, idx) in config.vector_stores" :key="idx" class="vector-store-item">
                        <strong>{{ store.name }}:</strong> <code>{{ store.id }}</code>
                      </div>
                    </div>
                    <div v-else class="config-text">Не задано</div>
                  </div>

                  <div class="config-section">
                    <h4>Модель OpenAI</h4>
                    <div class="config-text"><code>{{ config.openai_model || 'gpt-5-2025-08-07' }}</code></div>
                  </div>

                  <div class="config-section">
                    <h4>Service Tier</h4>
                    <div class="config-text">{{ config.openai_service_tier || 'flex' }}</div>
                  </div>
                </template>

                <template v-else>

                  <div class="form-group">
                    <label class="form-label">Приветственное сообщение (Kickoff)</label>
                    <textarea
                      v-model="configForm.kickoff_message"
                      class="form-textarea"
                      rows="6"
                      placeholder="Например: {owner_name_clean}, добрый день!&#10;&#10;Я — ИИ-ассистент Capital Mars..."
                    />
                    <small class="form-help">Первое сообщение, которое бот отправляет клиенту. Доступны переменные: {owner_name_clean}, {address}, {objectCount}, {price}</small>
                  </div>
                  
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

                  <div class="form-row">
                    <div class="form-group">
                      <label class="form-label">Модель OpenAI</label>
                      <input
                        v-model="configForm.openai_model"
                        type="text"
                        class="form-input"
                        placeholder="gpt-5-2025-08-07"
                      />
                      <small class="form-help">Модель OpenAI для генерации ответов (например: gpt-5-2025-08-07, gpt-4o, gpt-4-turbo)</small>
                    </div>

                    <div class="form-group">
                      <label class="form-label">Service Tier</label>
                      <select v-model="configForm.openai_service_tier" class="form-input">
                        <option value="auto">Auto</option>
                        <option value="default">Default</option>
                        <option value="flex">Flex</option>
                      </select>
                      <small class="form-help">Уровень сервиса OpenAI API</small>
                    </div>
                  </div>

                  <div class="form-group">
                    <label class="form-label">Vector Stores (базы знаний RAG)</label>
                    <div class="vector-stores-editor">
                      <div 
                        v-for="(store, index) in configForm.vector_stores" 
                        :key="index"
                        class="vector-store-row"
                      >
                        <input
                          v-model="store.name"
                          type="text"
                          class="form-input"
                          placeholder="Название (например: Основная база)"
                          style="flex: 1;"
                        />
                        <input
                          v-model="store.id"
                          type="text"
                          class="form-input"
                          placeholder="vs_..."
                          style="flex: 2;"
                        />
                        <button 
                          type="button"
                          class="btn btn--ghost btn--sm"
                          @click="removeVectorStore(index)"
                        >
                          🗑️
                        </button>
                      </div>
                      <button 
                        type="button"
                        class="btn btn--secondary btn--sm"
                        @click="addVectorStore"
                      >
                        ➕ Добавить Vector Store
                      </button>
                    </div>
                    <small class="form-help">Добавьте базы знаний для RAG (Retrieval-Augmented Generation). Каждая база будет использоваться для поиска релевантной информации.</small>
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
