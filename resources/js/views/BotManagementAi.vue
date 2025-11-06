<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import MainLayout from '@/layouts/MainLayout.vue';
import { useBotStore } from '@/stores/botStore';
import type { BotConfig, VectorStore } from '@/types';

const botStore = useBotStore();

const botConfig = ref<BotConfig | null>(null);
const isEditing = ref(false);
const isLoading = ref(true);
const loadError = ref<string | null>(null);

onMounted(async () => {
    try {
        isLoading.value = true;
        loadError.value = null;
        
        await botStore.fetchBotConfigs('whatsapp');
        console.log('Loaded configs:', botStore.configs);
        
        // Загружаем первую AI конфигурацию для WhatsApp, или любую первую если AI не найдена
        const aiConfig = botStore.configs.find(c => c.bot_type === 'ai') || botStore.configs[0];
        
        if (aiConfig) {
            console.log('Selected config:', aiConfig);
            botConfig.value = aiConfig;
            loadConfigToForm(aiConfig);
        } else {
            console.warn('No bot configs found');
            loadError.value = 'Не найдена конфигурация бота для WhatsApp. Пожалуйста, создайте конфигурацию.';
        }
    } catch (error) {
        console.error('Failed to load bot config:', error);
        loadError.value = 'Ошибка загрузки конфигурации: ' + (error as any)?.message || 'Неизвестная ошибка';
    } finally {
        isLoading.value = false;
    }
});

const configForm = ref<{
    prompt: string;
    max_tokens: number;
    kickoff_message: string;
    vector_stores: VectorStore[];
    openai_model: string;
    openai_service_tier: string;
}>({
    prompt: '',
    max_tokens: 2000,
    kickoff_message: '',
    vector_stores: [],
    openai_model: 'gpt-4o',
    openai_service_tier: 'flex',
});

const loading = computed(() => botStore.loading);

// Функция для нормализации специальных символов
const normalizeText = (text: string): string => {
    if (!text) return text;
    
    // Нормализуем Unicode символы (NFC - Canonical Composition)
    let normalized = text.normalize('NFC');
    
    // Заменяем проблемные символы из Private Use Area и другие варианты
    // U+F0B7 (Private Use Area) и другие варианты bullet point на стандартный U+2022
    normalized = normalized
        .replace(/\uF0B7/g, '•') // Private Use Area символ
        .replace(/[\u2022\u2023\u25E6\u2043\u2219\u00B7\u25CF]/g, '•') // Различные варианты bullet
        .replace(/\uFFFD/g, '•');
    
    return normalized;
};

const loadConfigToForm = (config: BotConfig) => {
    configForm.value = {
        prompt: normalizeText(config.prompt || ''),
        max_tokens: config.max_tokens || 2000,
        kickoff_message: normalizeText(config.kickoff_message || ''),
        vector_stores: config.vector_stores ? [...config.vector_stores] : [],
        openai_model: config.openai_model || 'gpt-4o',
        openai_service_tier: config.openai_service_tier || 'flex',
    };
};

const startEditing = () => {
    isEditing.value = true;
};

// Обработчик ввода для textarea промпта
const handlePromptInput = (event: Event) => {
    const target = event.target as HTMLTextAreaElement;
    if (target) {
        configForm.value.prompt = normalizeText(target.value);
    }
};

// Обработчик ввода для kickoff_message
const handleKickoffInput = (event: Event) => {
    const target = event.target as HTMLTextAreaElement;
    if (target) {
        configForm.value.kickoff_message = normalizeText(target.value);
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
    if (!botConfig.value) return;
    
    // Валидация для AI ботов
    if (!configForm.value.prompt || configForm.value.prompt.trim() === '') {
        alert('Заполните промпт для ChatGPT');
        return;
    }
    
    try {
        // Нормализуем текст перед сохранением
        const normalizedData = {
            ...configForm.value,
            prompt: normalizeText(configForm.value.prompt),
            kickoff_message: normalizeText(configForm.value.kickoff_message),
        };
        
        await botStore.updateBotConfig(botConfig.value.id, normalizedData);
        await botStore.fetchBotConfigs('whatsapp');
        
        // Перезагружаем конфигурацию
        const aiConfig = botStore.configs.find(c => c.bot_type === 'ai') || botStore.configs[0];
        if (aiConfig) {
            botConfig.value = aiConfig;
            loadConfigToForm(aiConfig);
        }
        
        isEditing.value = false;
    } catch (err) {
        console.error('Failed to save config:', err);
        alert('Ошибка сохранения конфигурации: ' + (err as any)?.response?.data?.message || 'Неизвестная ошибка');
    }
};

const cancelEditing = () => {
    if (botConfig.value) {
        loadConfigToForm(botConfig.value);
    }
    isEditing.value = false;
};
</script>

<template>
  <MainLayout>
    <div class="bot-management-page">
      <div class="page-header">
        <div>
          <h1>🤖 Управление AI-ботом WhatsApp</h1>
          <p>Настройка промптов и параметров для ChatGPT</p>
        </div>
        <div class="page-actions" v-if="botConfig && !isEditing">
          <button class="btn btn--primary" @click="startEditing">
            ✏️ Редактировать конфигурацию
          </button>
        </div>
      </div>

      <div v-if="isLoading" class="empty-state">
        <div class="loader"></div>
        <p>Загрузка конфигурации...</p>
      </div>

      <div v-else-if="loadError" class="error-state">
        <div class="error-icon">⚠️</div>
        <p>{{ loadError }}</p>
      </div>

      <div v-else-if="botConfig" class="bot-config-container">
        <!-- Режим просмотра -->
        <template v-if="!isEditing">
          <div class="config-section">
            <h3>Приветственное сообщение (Kickoff)</h3>
            <div class="config-text config-text--pre">{{ normalizeText(botConfig.kickoff_message || 'Не задано') }}</div>
          </div>

          <div class="config-section">
            <h3>Промпт для ChatGPT</h3>
            <div class="config-text config-text--pre">{{ normalizeText(botConfig.prompt || 'Не задано') }}</div>
          </div>

          <div class="config-section">
            <h3>Vector Stores (базы знаний RAG)</h3>
            <div v-if="botConfig.vector_stores && botConfig.vector_stores.length > 0" class="vector-stores-list">
              <div v-for="(store, idx) in botConfig.vector_stores" :key="idx" class="vector-store-item">
                <strong>{{ store.name }}:</strong> <code>{{ store.id }}</code>
              </div>
            </div>
            <div v-else class="config-text">Не задано</div>
          </div>

          <div class="config-row">
            <div class="config-section">
              <h3>Модель OpenAI</h3>
              <div class="config-text"><code>{{ botConfig.openai_model || 'gpt-4o' }}</code></div>
            </div>

            <div class="config-section">
              <h3>Service Tier</h3>
              <div class="config-text">{{ botConfig.openai_service_tier || 'flex' }}</div>
            </div>

            <div class="config-section">
              <h3>Max Tokens</h3>
              <div class="config-text">{{ botConfig.max_tokens || 2000 }}</div>
            </div>
          </div>
        </template>

        <!-- Режим редактирования -->
        <template v-else>
          <div class="form-group">
            <label class="form-label">Приветственное сообщение (Kickoff)</label>
            <textarea
              :value="configForm.kickoff_message"
              @input="handleKickoffInput"
              class="form-textarea"
              rows="6"
              placeholder="Например: {owner_name_clean}, добрый день!&#10;&#10;Я — ИИ-ассистент Capital Mars..."
              style="white-space: pre-wrap; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;"
            />
            <small class="form-help">Первое сообщение, которое бот отправляет клиенту. Доступны переменные: {owner_name_clean}, {address}, {objectCount}, {price}</small>
          </div>
          
          <div class="form-group">
            <label class="form-label">Промпт для ChatGPT *</label>
            <textarea
              :value="configForm.prompt"
              @input="handlePromptInput"
              class="form-textarea"
              rows="40"
              placeholder="Введите системный промпт для ChatGPT..."
              style="white-space: pre-wrap; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;"
            />
            <small class="form-help">Этот промпт определяет поведение и стиль ответов бота</small>
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

          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Модель OpenAI</label>
              <input
                v-model="configForm.openai_model"
                type="text"
                class="form-input"
                placeholder="gpt-4o"
              />
              <small class="form-help">Модель OpenAI для генерации ответов (например: gpt-4o, gpt-4o-mini, gpt-4-turbo)</small>
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

          <div class="form-actions">
            <button class="btn btn--ghost" @click="cancelEditing">Отмена</button>
            <button class="btn btn--primary" @click="saveConfig" :disabled="loading">
              💾 Сохранить
            </button>
          </div>
        </template>
      </div>

    </div>
  </MainLayout>
</template>

<style scoped lang="scss">
.bot-management-page {
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

.empty-state {
  text-align: center;
  padding: 3rem 2rem;
  color: #666;
  font-size: 1.1rem;

  .loader {
    margin: 0 auto 1rem;
    width: 50px;
    height: 50px;
    border: 4px solid #f3f3f3;
    border-top: 4px solid #007bff;
    border-radius: 50%;
    animation: spin 1s linear infinite;
  }
}

.error-state {
  text-align: center;
  padding: 3rem 2rem;
  color: #dc3545;
  font-size: 1.1rem;

  .error-icon {
    font-size: 3rem;
    margin-bottom: 1rem;
  }

  p {
    color: #666;
  }
}

@keyframes spin {
  0% { transform: rotate(0deg); }
  100% { transform: rotate(360deg); }
}

.bot-config-container {
  max-width: 1200px;
  background: white;
  border-radius: 8px;
  padding: 2rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
}

.config-section {
  margin-bottom: 2rem;

  h3 {
    font-size: 1.1rem;
    font-weight: 600;
    margin-bottom: 0.75rem;
    color: #333;
  }

  .config-text {
    padding: 1rem;
    background: #f8f9fa;
    border-radius: 6px;
    color: #495057;
    line-height: 1.6;

    &--pre {
      white-space: pre-wrap;
      font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
    }

    code {
      background: #e9ecef;
      padding: 0.2rem 0.4rem;
      border-radius: 3px;
      font-size: 0.9rem;
    }
  }
}

.config-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
  margin-bottom: 2rem;
}

.vector-stores-list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;

  .vector-store-item {
    padding: 0.75rem;
    background: #f8f9fa;
    border-radius: 4px;
    
    strong {
      color: #495057;
      margin-right: 0.5rem;
    }

    code {
      background: #e9ecef;
      padding: 0.2rem 0.4rem;
      border-radius: 3px;
      font-size: 0.9rem;
    }
  }
}

.form-group {
  margin-bottom: 1.5rem;

  .form-label {
    display: block;
    font-weight: 600;
    margin-bottom: 0.5rem;
    color: #333;
  }

  .form-textarea,
  .form-input {
    width: 100%;
    padding: 0.75rem;
    border: 1px solid #ced4da;
    border-radius: 6px;
    font-size: 0.95rem;
    transition: border-color 0.15s;

    &:focus {
      outline: none;
      border-color: #007bff;
      box-shadow: 0 0 0 3px rgba(0, 123, 255, 0.1);
    }
  }

  .form-textarea {
    resize: vertical;
    min-height: 100px;
    font-family: inherit;
  }

  .form-help {
    display: block;
    margin-top: 0.5rem;
    font-size: 0.875rem;
    color: #6c757d;
  }
}

.form-row {
  display: grid;
  grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
  gap: 1.5rem;
}

.vector-stores-editor {
  display: flex;
  flex-direction: column;
  gap: 0.75rem;

  .vector-store-row {
    display: flex;
    gap: 0.75rem;
    align-items: center;
  }
}

.form-actions {
  display: flex;
  justify-content: flex-end;
  gap: 1rem;
  margin-top: 2rem;
  padding-top: 1.5rem;
  border-top: 1px solid #e0e0e0;
}

.btn {
  padding: 0.625rem 1.25rem;
  border: none;
  border-radius: 6px;
  font-size: 0.95rem;
  font-weight: 500;
  cursor: pointer;
  transition: all 0.2s;
  display: inline-flex;
  align-items: center;
  gap: 0.5rem;

  &:disabled {
    opacity: 0.5;
    cursor: not-allowed;
  }

  &--primary {
    background: #007bff;
    color: white;

    &:hover:not(:disabled) {
      background: #0056b3;
    }
  }

  &--secondary {
    background: #6c757d;
    color: white;

    &:hover:not(:disabled) {
      background: #5a6268;
    }
  }

  &--ghost {
    background: transparent;
    color: #6c757d;
    border: 1px solid #ced4da;

    &:hover:not(:disabled) {
      background: #f8f9fa;
      color: #495057;
    }
  }

  &--sm {
    padding: 0.4rem 0.8rem;
    font-size: 0.875rem;
  }
}
</style>
